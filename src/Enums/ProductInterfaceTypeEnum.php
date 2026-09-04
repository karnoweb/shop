<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

use Karnoweb\Shop\Models\ProductInterface;

/**
 * Variant/configuration shape of a {@see ProductInterface}.
 *
 * Inventory/sell behavior lives on {@see ProductKindEnum}, not here.
 */
enum ProductInterfaceTypeEnum: string
{
    case SIMPLE = 'simple';
    case CODDING = 'codding';

    public function title(): string
    {
        return match ($this) {
            self::SIMPLE => __('shop::shop.product_interface.type.simple'),
            self::CODDING => __('shop::shop.product_interface.type.codding'),
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
