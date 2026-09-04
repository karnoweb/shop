<?php

declare(strict_types=1);

namespace Karnoweb\Shop\DTOs;

use Karnoweb\Shop\Services\QuoteService;

/**
 * Immutable, portable price quote for a single product.
 *
 * Produced by {@see QuoteService} /
 * `Shop::quote()->resolve()`. Pure data — safe to serialize and hand off to
 * host/commerce checkout flows via {@see self::toCommerceSnapshot()} without
 * this package (or the caller) depending on `karnoweb/commerce` classes.
 */
final readonly class PriceQuote
{
    public function __construct(
        public int $productId,
        public int $unitPrice,
        public int $basePrice,
        public int $finalPrice,
        public bool $hasDiscount,
        public int|float|null $discountPercent,
        public ?int $campaignId,
        public ?string $tier,
        public ?int $userGroupId,
        public string $source,
    ) {}

    /**
     * Pure array snapshot safe to store in e.g. commerce `OrderItem.extra_attributes`.
     *
     * Must never reference Commerce (or any other host/domain) classes — this
     * is a plain array so the host decides how/where to persist it.
     *
     * @return array{
     *     product_id: int,
     *     unit_price: int,
     *     base_price: int,
     *     final_price: int,
     *     has_discount: bool,
     *     discount_percent: int|float|null,
     *     campaign_id: int|null,
     *     tier: string|null,
     *     user_group_id: int|null,
     *     source: string,
     * }
     */
    public function toCommerceSnapshot(): array
    {
        return [
            'product_id' => $this->productId,
            'unit_price' => $this->unitPrice,
            'base_price' => $this->basePrice,
            'final_price' => $this->finalPrice,
            'has_discount' => $this->hasDiscount,
            'discount_percent' => $this->discountPercent,
            'campaign_id' => $this->campaignId,
            'tier' => $this->tier,
            'user_group_id' => $this->userGroupId,
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
            unitPrice: (int) $data['unit_price'],
            basePrice: (int) $data['base_price'],
            finalPrice: (int) $data['final_price'],
            hasDiscount: (bool) $data['has_discount'],
            discountPercent: $data['discount_percent'] ?? null,
            campaignId: isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            tier: $data['tier'] ?? null,
            userGroupId: isset($data['user_group_id']) ? (int) $data['user_group_id'] : null,
            source: (string) $data['source'],
        );
    }
}
