<?php

declare(strict_types=1);

namespace Karnoweb\Shop\DTOs;

use Karnoweb\Shop\Services\ProductPriceResolver;
use Karnoweb\Shop\Services\QuoteService;

/**
 * Immutable, portable price quote for a single product.
 *
 * Produced by {@see QuoteService} / `Shop::quote()->resolve()`. Pure data —
 * safe to serialize and hand off to host/commerce checkout flows via
 * {@see self::toCommerceSnapshot()} without this package (or the caller)
 * depending on `karnoweb/commerce` classes.
 *
 * `basePrice` is the price resolved by {@see ProductPriceResolver}
 * for the given `tier`/`userGroupId` — i.e. before any campaign adjustment —
 * and `source` reports which strategy produced it (see {@see self::$source}).
 * `finalPrice` is `basePrice` after an optional campaign adjustment; they are
 * equal when no `CampaignPriceAdjuster` is bound.
 */
final readonly class PriceQuote
{
    public function __construct(
        public int $productId,
        public ?string $tier,
        public ?int $userGroupId,
        public int $basePrice,
        public int $finalPrice,
        public bool $hasDiscount,
        public int $discountAmount,
        public ?float $discountPercent,
        public ?int $campaignId,
        /**
         * Which pricing strategy produced `basePrice`:
         * "user_group_price"|"tier_price"|"default_price"|"base_price".
         */
        public string $source,
    ) {}

    /**
     * Pure array snapshot safe to store in e.g. commerce `OrderItem.extra_attributes`.
     *
     * Must never reference Commerce (or any other host/domain) classes — this
     * is a plain array of scalars/null so the host decides how/where to
     * persist it. Keys are stable and documented in docs/usage.md.
     *
     * @return array{
     *     product_id: int,
     *     tier: string|null,
     *     user_group_id: int|null,
     *     base_price: int,
     *     final_price: int,
     *     has_discount: bool,
     *     discount_amount: int,
     *     discount_percent: float|null,
     *     campaign_id: int|null,
     *     source: string,
     * }
     */
    public function toCommerceSnapshot(): array
    {
        return [
            'product_id' => $this->productId,
            'tier' => $this->tier,
            'user_group_id' => $this->userGroupId,
            'base_price' => $this->basePrice,
            'final_price' => $this->finalPrice,
            'has_discount' => $this->hasDiscount,
            'discount_amount' => $this->discountAmount,
            'discount_percent' => $this->discountPercent,
            'campaign_id' => $this->campaignId,
            'source' => $this->source,
        ];
    }

    /**
     * Rebuild a PriceQuote from a previously stored {@see self::toCommerceSnapshot()} array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            tier: $data['tier'] ?? null,
            userGroupId: isset($data['user_group_id']) ? (int) $data['user_group_id'] : null,
            basePrice: (int) $data['base_price'],
            finalPrice: (int) $data['final_price'],
            hasDiscount: (bool) $data['has_discount'],
            discountAmount: (int) ($data['discount_amount'] ?? 0),
            discountPercent: isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
            campaignId: isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            source: (string) $data['source'],
        );
    }
}
