<?php

declare(strict_types=1);

namespace Karnoweb\Shop\DTOs;

use Karnoweb\Shop\Models\Product;
use Karnoweb\Shop\Services\ProductPriceResolver;
use Karnoweb\Shop\Services\QuoteService;

/**
 * Immutable, portable price quote for a single sellable item.
 *
 * Produced by {@see QuoteService} / `Shop::quote()->resolve()`. Pure data —
 * safe to serialize and hand off to host/commerce checkout flows via
 * {@see self::toCommerceSnapshot()} without this package (or the caller)
 * depending on `karnoweb/commerce` classes.
 *
 * Deliberately generic: `itemType`/`itemId` describe *what* is being quoted
 * without assuming "product" is the only sellable thing Commerce will ever
 * store a snapshot for. Today `QuoteService` only resolves catalog
 * {@see Product} rows, so `itemType` defaults to and is
 * currently always `"shop.product"` (with `itemId` === `productId`) — but the
 * shape leaves room for future item types (e.g. `"shop.variant"`,
 * `"shop.module"`) without a schema/contract change.
 *
 * `basePrice` is the price resolved by {@see ProductPriceResolver}
 * for the given `tier`/`segmentId` — i.e. before any campaign adjustment —
 * and `source` reports which strategy produced it (see {@see self::$source}).
 * `finalPrice` is `basePrice` after an optional campaign adjustment; they are
 * equal when no `CampaignPriceAdjuster` is bound.
 */
final readonly class PriceQuote
{
    public function __construct(
        public int $productId,
        public ?string $tier,
        /** Soft host segmentation key (typically a user-group id). */
        public ?int $segmentId,
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
        /**
         * Generic sellable-item type. Currently always "shop.product" — see
         * class docblock. Never a Commerce (or other host/domain) class.
         */
        public string $itemType = 'shop.product',
        /** Same as `productId` for the current "shop.product" item type. */
        public ?int $itemId = null,
        /** Optional default unit-of-measure code (e.g. "kg", "pcs"), when known. */
        public ?string $uomCode = null,
    ) {}

    /**
     * Pure array snapshot safe to store in e.g. commerce `OrderItem.extra_attributes`.
     *
     * Must never reference Commerce (or any other host/domain) classes — this
     * is a plain array of scalars/null so the host decides how/where to
     * persist it. Keys are stable and documented in docs/usage.md.
     *
     * `item_type`/`item_id` are the generic, forward-compatible reference to
     * the quoted sellable; `product_id` is kept alongside them for backward
     * compatibility with existing snapshot consumers. `segment_id` is the
     * canonical key for the soft host segmentation id; `user_group_id` is
     * kept alongside it (same value) since it was already published.
     *
     * @return array{
     *     item_type: string,
     *     item_id: int|null,
     *     product_id: int,
     *     tier: string|null,
     *     segment_id: int|null,
     *     user_group_id: int|null,
     *     base_price: int,
     *     final_price: int,
     *     has_discount: bool,
     *     discount_amount: int,
     *     discount_percent: float|null,
     *     campaign_id: int|null,
     *     source: string,
     *     uom_code: string|null,
     * }
     */
    public function toCommerceSnapshot(): array
    {
        return [
            'item_type' => $this->itemType,
            'item_id' => $this->itemId,
            'product_id' => $this->productId,
            'tier' => $this->tier,
            'segment_id' => $this->segmentId,
            // Backward-compatible alias — already published, same value as `segment_id`.
            'user_group_id' => $this->segmentId,
            'base_price' => $this->basePrice,
            'final_price' => $this->finalPrice,
            'has_discount' => $this->hasDiscount,
            'discount_amount' => $this->discountAmount,
            'discount_percent' => $this->discountPercent,
            'campaign_id' => $this->campaignId,
            'source' => $this->source,
            'uom_code' => $this->uomCode,
        ];
    }

    /**
     * Rebuild a PriceQuote from a previously stored {@see self::toCommerceSnapshot()} array.
     *
     * Tolerates snapshots stored before `item_type`/`item_id`/`uom_code`
     * existed — falls back to `"shop.product"`/`product_id`/`null`. Prefers
     * the canonical `segment_id` key, falling back to the legacy
     * `user_group_id` key for snapshots stored before the rename.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $productId = (int) $data['product_id'];

        $segmentId = $data['segment_id'] ?? $data['user_group_id'] ?? null;

        return new self(
            productId: $productId,
            tier: $data['tier'] ?? null,
            segmentId: $segmentId !== null ? (int) $segmentId : null,
            basePrice: (int) $data['base_price'],
            finalPrice: (int) $data['final_price'],
            hasDiscount: (bool) $data['has_discount'],
            discountAmount: (int) ($data['discount_amount'] ?? 0),
            discountPercent: isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
            campaignId: isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            source: (string) $data['source'],
            itemType: (string) ($data['item_type'] ?? 'shop.product'),
            itemId: isset($data['item_id']) ? (int) $data['item_id'] : $productId,
            uomCode: $data['uom_code'] ?? null,
        );
    }
}
