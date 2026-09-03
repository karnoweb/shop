<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

enum CampaignTypeEnum: string
{
    case PRODUCT_BASED = 'product_based';
    case ORDER_BASED = 'order_based';

    public function title(): string
    {
        return match ($this) {
            self::PRODUCT_BASED => __('shop::shop.campaign.type.product_based'),
            self::ORDER_BASED => __('shop::shop.campaign.type.order_based'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PRODUCT_BASED => 'o-cube',
            self::ORDER_BASED => 'o-shopping-cart',
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
