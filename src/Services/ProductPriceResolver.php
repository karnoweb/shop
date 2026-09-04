<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Karnoweb\Shop\Support\Money;
use Karnoweb\Shop\Support\ShopContext;

/**
 * Resolve product price by product, currency, branch, segment, and tier.
 *
 * Preference (after a required currency match):
 * 1. branch_id: exact branch, else null (when branch_mode = inherit_global)
 * 2. segment_id: exact match, else null
 * 3. tier: exact match, else null
 * 4. Fallback to product.base_price
 *
 * `$userGroupId` here is a soft host key persisted on the `segment_id` DB
 * column — the parameter/method names are kept as documented public API.
 */
class ProductPriceResolver
{
    /**
     * Backward-compatible adapter over {@see self::resolveForUserGroupId()}.
     *
     * @param  Model  $product  Product model instance
     * @param  object|null  $user  User with optional profile->user_group_id
     * @param  string|null  $tier  Optional portable price tier (e.g. retail, wholesale)
     */
    public function resolve(Model $product, ?object $user = null, ?string $tier = null): int
    {
        $userGroupId = data_get($user, 'profile.user_group_id');

        return $this->resolveForUserGroupId(
            $product,
            $userGroupId !== null ? (int) $userGroupId : null,
            $tier
        );
    }

    /**
     * Same resolve order as {@see self::resolve()}, but takes an explicit
     * user group id instead of a user object.
     *
     * @param  Model  $product  Product model instance
     * @param  int|null  $userGroupId  Soft host user-group key, or null
     * @param  string|null  $tier  Optional portable price tier (e.g. retail, wholesale)
     */
    public function resolveForUserGroupId(
        Model $product,
        ?int $userGroupId,
        ?string $tier = null,
        ?string $currency = null,
        ?int $branchId = null,
    ): int {
        return $this->resolveDetailedForUserGroupId($product, $userGroupId, $tier, $currency, $branchId)['price'];
    }

    /**
     * Same resolve order as {@see self::resolveForUserGroupId()}, but also
     * reports which strategy matched.
     *
     * @param  Model  $product  Product model instance
     * @param  int|null  $userGroupId  Soft host user-group key, or null
     * @param  string|null  $tier  Optional portable price tier (e.g. retail, wholesale)
     * @return array{price: int, source: string} source is one of:
     *                                           user_group_price|tier_price|default_price|base_price
     */
    public function resolveDetailedForUserGroupId(
        Model $product,
        ?int $userGroupId,
        ?string $tier = null,
        ?string $currency = null,
        ?int $branchId = null,
    ): array {
        $currency ??= Money::defaultCurrency();
        $branchId ??= app(ShopContext::class)->branchId();

        /** @var class-string<Model> $priceClass */
        $priceClass = config('shop.models.product_price');

        $candidates = $priceClass::query()
            ->where('product_id', $product->getKey())
            ->where('currency', $currency)
            ->forBranch($branchId)
            ->active()
            ->get();

        $best = $this->pickPreferred($candidates, $branchId, $userGroupId, $tier);

        if ($best === null) {
            return ['price' => (int) $product->getAttribute('base_price'), 'source' => 'base_price'];
        }

        return [
            'price' => (int) $best->getAttribute('price'),
            'source' => $this->sourceFor($best, $userGroupId, $tier),
        ];
    }

    /**
     * @param  Collection<int, Model>  $candidates
     */
    private function pickPreferred($candidates, ?int $branchId, ?int $segmentId, ?string $tier): ?Model
    {
        $scored = $candidates
            ->map(function (Model $row) use ($branchId, $segmentId, $tier): ?array {
                $score = $this->scoreRow($row, $branchId, $segmentId, $tier);

                return $score === null ? null : ['row' => $row, 'score' => $score];
            })
            ->filter()
            ->sortByDesc(fn (array $item): int => $item['score'])
            ->first();

        return $scored['row'] ?? null;
    }

    /**
     * Higher score wins. Ineligible rows (wrong exclusive match) return null.
     *
     * Branch: exact preferred, then null (inherit_global already filtered the query).
     * Segment: exact preferred, then null.
     * Tier: exact preferred, then null.
     */
    private function scoreRow(Model $row, ?int $branchId, ?int $segmentId, ?string $tier): ?int
    {
        $rowBranch = $row->getAttribute('branch_id');
        $rowSegment = $row->getAttribute('segment_id');
        $rowTier = $row->getAttribute('tier');

        if ($segmentId === null && $rowSegment !== null) {
            return null;
        }

        if ($segmentId !== null && $rowSegment !== null && (int) $rowSegment !== $segmentId) {
            return null;
        }

        if ($tier !== null && $rowTier !== null && $rowTier !== $tier) {
            return null;
        }

        $score = 0;

        if ($branchId !== null && $rowBranch !== null && (int) $rowBranch === $branchId) {
            $score += 100;
        }

        if ($segmentId !== null && $rowSegment !== null && (int) $rowSegment === $segmentId) {
            $score += 10;
        }

        if ($tier !== null && $rowTier === $tier) {
            $score += 1;
        }

        return $score;
    }

    private function sourceFor(Model $row, ?int $segmentId, ?string $tier): string
    {
        $rowSegment = $row->getAttribute('segment_id');
        $rowTier = $row->getAttribute('tier');

        if ($segmentId !== null && $rowSegment !== null && (int) $rowSegment === $segmentId) {
            return 'user_group_price';
        }

        if ($tier !== null && $rowTier === $tier) {
            return 'tier_price';
        }

        return 'default_price';
    }
}
