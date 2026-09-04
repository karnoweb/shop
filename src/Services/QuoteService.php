<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Services;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Shop\Contracts\CampaignPriceAdjuster;
use Karnoweb\Shop\DTOs\PriceQuote;

/**
 * Produce a portable {@see PriceQuote} DTO for checkout handoff.
 *
 * Pure catalog concern: resolves the base price via {@see ProductPriceResolver}
 * and, if a host bridge is bound, asks {@see CampaignPriceAdjuster} for a final
 * price. Never assumes `auth()->user()` — callers pass `userGroupId`/`tier`
 * explicitly, and never imports host (`App\*`) or commerce classes.
 */
readonly class QuoteService
{
    public function __construct(
        private ProductPriceResolver $priceResolver,
        private ?CampaignPriceAdjuster $campaignPriceAdjuster = null,
    ) {}

    /**
     * @param  Model  $product  Catalog product (host or package model)
     * @param  int|null  $userGroupId  Soft host user-group key, or null
     * @param  string|null  $tier  Optional portable price tier (e.g. retail, wholesale)
     * @param  string  $itemType  Generic sellable-item type for the resulting snapshot
     *                            (see {@see PriceQuote}); defaults to "shop.product".
     */
    public function resolve(
        Model $product,
        ?int $userGroupId = null,
        ?string $tier = null,
        string $itemType = 'shop.product',
        ?string $currency = null,
        ?int $branchId = null,
    ): PriceQuote {
        $detail = $this->priceResolver->resolveDetailedForUserGroupId(
            $product,
            $userGroupId,
            $tier,
            $currency,
            $branchId,
        );

        $basePrice = $detail['price'];
        $source = $detail['source'];

        $finalPrice = $basePrice;
        $hasDiscount = false;
        $discountAmount = 0;
        $discountPercent = null;
        $campaignId = null;

        if ($this->campaignPriceAdjuster !== null) {
            $adjusted = $this->campaignPriceAdjuster->adjust($product, null, $basePrice);

            if ($adjusted !== null) {
                $finalPrice = (int) ($adjusted['final_price'] ?? $basePrice);
                $hasDiscount = (bool) ($adjusted['has_discount'] ?? false);
                $campaignId = isset($adjusted['campaign_id']) ? (int) $adjusted['campaign_id'] : null;
                $discountAmount = max(0, $basePrice - $finalPrice);

                $discountPercent = isset($adjusted['discount_percent'])
                    ? (float) $adjusted['discount_percent']
                    : ($discountAmount > 0 && $basePrice > 0
                        ? round(($discountAmount / $basePrice) * 100, 2)
                        : null);
            }
        }

        $productId = (int) $product->getKey();

        return new PriceQuote(
            productId: $productId,
            tier: $tier,
            segmentId: $userGroupId,
            basePrice: $basePrice,
            finalPrice: $finalPrice,
            hasDiscount: $hasDiscount,
            discountAmount: $discountAmount,
            discountPercent: $discountPercent,
            campaignId: $campaignId,
            source: $source,
            itemType: $itemType,
            itemId: $productId,
            uomCode: $product->getAttribute('default_uom_code'),
        );
    }
}
