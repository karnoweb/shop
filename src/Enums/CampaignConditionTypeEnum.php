<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

enum CampaignConditionTypeEnum: string
{
    case CATEGORY = 'category';
    case BRAND = 'brand';
    case PRODUCT = 'product';
    case USER = 'user';
    case USER_GROUP = 'user_group';
    case MIN_ORDER_AMOUNT = 'min_order_amount';
    case MIN_ORDER_COUNT = 'min_order_count';
    case FIRST_ORDER = 'first_order';
    case DATE_RANGE = 'date_range';

    public function title(): string
    {
        return match ($this) {
            self::CATEGORY => __('shop::shop.campaign.condition.category'),
            self::BRAND => __('shop::shop.campaign.condition.brand'),
            self::PRODUCT => __('shop::shop.campaign.condition.product'),
            self::USER => __('shop::shop.campaign.condition.user'),
            self::USER_GROUP => __('shop::shop.campaign.condition.user_group'),
            self::MIN_ORDER_AMOUNT => __('shop::shop.campaign.condition.min_order_amount'),
            self::MIN_ORDER_COUNT => __('shop::shop.campaign.condition.min_order_count'),
            self::FIRST_ORDER => __('shop::shop.campaign.condition.first_order'),
            self::DATE_RANGE => __('shop::shop.campaign.condition.date_range'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CATEGORY => 'o-folder',
            self::BRAND => 'o-building-storefront',
            self::PRODUCT => 'o-cube',
            self::USER => 'o-user',
            self::USER_GROUP => 'o-user-group',
            self::MIN_ORDER_AMOUNT => 'o-currency-dollar',
            self::MIN_ORDER_COUNT => 'o-shopping-cart',
            self::FIRST_ORDER => 'o-gift',
            self::DATE_RANGE => 'o-calendar',
        };
    }

    public function requiresValue(): bool
    {
        return $this !== self::FIRST_ORDER;
    }

    public function valueType(): string
    {
        return match ($this) {
            self::CATEGORY, self::BRAND, self::PRODUCT, self::USER, self::USER_GROUP => 'select',
            self::MIN_ORDER_AMOUNT => 'number',
            self::MIN_ORDER_COUNT => 'integer',
            self::FIRST_ORDER => 'boolean',
            self::DATE_RANGE => 'daterange',
        };
    }

    public function isProductBased(): bool
    {
        return match ($this) {
            self::CATEGORY, self::BRAND, self::PRODUCT => true,
            default => false,
        };
    }

    public function isOrderBased(): bool
    {
        return match ($this) {
            self::USER, self::USER_GROUP, self::MIN_ORDER_AMOUNT, self::MIN_ORDER_COUNT, self::FIRST_ORDER, self::DATE_RANGE => true,
            default => false,
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
