<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

use Karnoweb\Shop\Models\ProductInterface;

/**
 * Inventory/sell behavior classification for a
 * {@see ProductInterface}.
 *
 * Independent from {@see ProductInterfaceTypeEnum}, which describes only the
 * variant/configuration shape (simple vs coding axes).
 */
enum ProductKindEnum: string
{
    case SIMPLE = 'simple';
    case INGREDIENT = 'ingredient';
    case COMPOSED = 'composed';
    case VIRTUAL = 'virtual';
    case BUNDLE = 'bundle';

    public function title(): string
    {
        return match ($this) {
            self::SIMPLE => __('shop::shop.product_interface.kind.simple'),
            self::INGREDIENT => __('shop::shop.product_interface.kind.ingredient'),
            self::COMPOSED => __('shop::shop.product_interface.kind.composed'),
            self::VIRTUAL => __('shop::shop.product_interface.kind.virtual'),
            self::BUNDLE => __('shop::shop.product_interface.kind.bundle'),
        };
    }

    /**
     * @return array{value: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->title(),
        ];
    }
}
