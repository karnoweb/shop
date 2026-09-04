<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolve product price based on user group and active date windows.
 *
 * Logic order:
 * 1. Active price for (product + user_group) — if user has group
 * 2. Active price for (product + null group) — default price
 * 3. Fallback to product.base_price
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
     * user group id instead of a user object — no `auth()`/`data_get($user, ...)`
     * assumption, so callers (e.g. {@see QuoteService})
     * can resolve prices without a host user model.
     *
     * @param  Model  $product  Product model instance
     * @param  int|null  $userGroupId  Soft host user-group key, or null
     * @param  string|null  $tier  Optional portable price tier (e.g. retail, wholesale)
     */
    public function resolveForUserGroupId(Model $product, ?int $userGroupId, ?string $tier = null): int
    {
        return $this->resolveDetailedForUserGroupId($product, $userGroupId, $tier)['price'];
    }

    /**
     * Same resolve order as {@see self::resolveForUserGroupId()}, but also
     * reports which strategy matched — useful for quote/audit surfaces that
     * need to explain where a price came from.
     *
     * @param  Model  $product  Product model instance
     * @param  int|null  $userGroupId  Soft host user-group key, or null
     * @param  string|null  $tier  Optional portable price tier (e.g. retail, wholesale)
     * @return array{price: int, source: string} source is one of: user_group|tier|default|base_price
     */
    public function resolveDetailedForUserGroupId(Model $product, ?int $userGroupId, ?string $tier = null): array
    {
        /** @var class-string<Model> $priceClass */
        $priceClass = config('shop.models.product_price');

        if ($userGroupId !== null) {
            $groupPrice = $priceClass::query()
                ->where('product_id', $product->getKey())
                ->where('user_group_id', $userGroupId)
                ->active()
                ->latest('starts_at')
                ->first();

            if ($groupPrice) {
                return ['price' => (int) $groupPrice->price, 'source' => 'user_group'];
            }
        }

        if ($tier !== null) {
            $tierPrice = $priceClass::query()
                ->where('product_id', $product->getKey())
                ->forTier($tier)
                ->active()
                ->latest('starts_at')
                ->first();

            if ($tierPrice) {
                return ['price' => (int) $tierPrice->price, 'source' => 'tier'];
            }
        }

        $defaultPrice = $priceClass::query()
            ->where('product_id', $product->getKey())
            ->whereNull('user_group_id')
            ->active()
            ->latest('starts_at')
            ->first();

        if ($defaultPrice) {
            return ['price' => (int) $defaultPrice->price, 'source' => 'default'];
        }

        return ['price' => (int) $product->getAttribute('base_price'), 'source' => 'base_price'];
    }
}
