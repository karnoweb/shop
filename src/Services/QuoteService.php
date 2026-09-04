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
     */
    public function resolve(Model $product, ?int $userGroupId = null, ?string $tier = null): PriceQuote
    {
        $basePrice = (int) $product->getAttribute('base_price');

        $detail = $this->priceResolver->resolveDetailedForUserGroupId($product, $userGroupId, $tier);

        $unitPrice = $detail['price'];
        $source = $detail['source'];

        $finalPrice = $unitPrice;
        $hasDiscount = false;
        $discountPercent = null;
        $campaignId = null;

        if ($this->campaignPriceAdjuster !== null) {
            $adjusted = $this->campaignPriceAdjuster->adjust($product, null, $unitPrice);

            if ($adjusted !== null) {
                $finalPrice = (int) ($adjusted['final_price'] ?? $unitPrice);
                $hasDiscount = (bool) ($adjusted['has_discount'] ?? false);
                $discountPercent = $adjusted['discount_percent'] ?? null;
                $campaignId = isset($adjusted['campaign_id']) ? (int) $adjusted['campaign_id'] : null;
            }
        }

        return new PriceQuote(
            productId: (int) $product->getKey(),
            unitPrice: $unitPrice,
            basePrice: $basePrice,
            finalPrice: $finalPrice,
            hasDiscount: $hasDiscount,
            discountPercent: $discountPercent,
            campaignId: $campaignId,
            tier: $tier,
            userGroupId: $userGroupId,
            source: $source,
        );
    }
}
