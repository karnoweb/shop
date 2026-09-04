<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

enum CampaignTypeEnum: string
{
    case PRODUCT_BASED = 'product_based';

    /**
     * @deprecated Order-lifecycle campaigns are a host/`karnoweb/commerce`
     * concern, not a catalog one — kept only for existing rows. Prefer
     * {@see self::PRICE_ADJUSTMENT} for new campaigns.
     */
    case ORDER_BASED = 'order_based';

    /**
     * Catalog/pricing-scoped: this campaign only adjusts a resolved price
     * (via the host's `CampaignPriceAdjuster` bridge) — no order-lifecycle
     * semantics (cart totals, order counts, ...) are modeled by this
     * package. This is the schema default.
     */
    case PRICE_ADJUSTMENT = 'price_adjustment';

    public function title(): string
    {
        return match ($this) {
            self::PRODUCT_BASED => __('shop::shop.campaign.type.product_based'),
            self::ORDER_BASED => __('shop::shop.campaign.type.order_based'),
            self::PRICE_ADJUSTMENT => __('shop::shop.campaign.type.price_adjustment'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PRODUCT_BASED => 'o-cube',
            self::ORDER_BASED => 'o-shopping-cart',
            self::PRICE_ADJUSTMENT => 'o-tag',
        };
    }

    /**
     * @return list<array{value: string, label: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->title(),
                'icon' => $case->icon(),
            ],
            self::cases()
        );
    }
}
