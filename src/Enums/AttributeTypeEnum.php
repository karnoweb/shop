<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

enum AttributeTypeEnum: string
{
    case TEXT = 'text';
    case COLOR = 'color';
    case SELECT = 'select';
    case NUMBER = 'number';
    case CHECKBOX = 'checkbox';

    public function title(): string
    {
        return match ($this) {
            self::TEXT => __('shop::shop.attribute.type.text'),
            self::COLOR => __('shop::shop.attribute.type.color'),
            self::SELECT => __('shop::shop.attribute.type.select'),
            self::NUMBER => __('shop::shop.attribute.type.number'),
            self::CHECKBOX => __('shop::shop.attribute.type.checkbox'),
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
