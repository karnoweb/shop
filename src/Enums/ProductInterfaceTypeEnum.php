<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

enum ProductInterfaceTypeEnum: string
{
    case SIMPLE = 'simple';
    case CODDING = 'codding';
    case DIGITAL = 'digital';
    case SERVICE = 'service';

    public function title(): string
    {
        return match ($this) {
            self::SIMPLE => __('shop::shop.product_interface.type.simple'),
            self::CODDING => __('shop::shop.product_interface.type.codding'),
            self::DIGITAL => __('shop::shop.product_interface.type.digital'),
            self::SERVICE => __('shop::shop.product_interface.type.service'),
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
