<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

use Karnoweb\Shop\Models\ProductInterface;

/**
 * Generic, inventory-agnostic business classification for a
 * {@see ProductInterface}.
 *
 * This is deliberately decoupled from stock/inventory concerns — it is pure
 * catalog metadata for "what kind of thing is this to a business", not "how
 * is it stocked" (that stays with `karnoweb/laravel-inventory` on the host).
 * It is also independent from {@see ProductInterfaceTypeEnum}, which
 * describes the variant/configuration shape (simple/codding/...), not the
 * business kind.
 */
enum ProductKindEnum: string
{
    case PHYSICAL = 'physical';
    case SERVICE = 'service';
    case DIGITAL = 'digital';
    case BUNDLE = 'bundle';

    public function title(): string
    {
        return match ($this) {
            self::PHYSICAL => __('shop::shop.product_interface.kind.physical'),
            self::SERVICE => __('shop::shop.product_interface.kind.service'),
            self::DIGITAL => __('shop::shop.product_interface.kind.digital'),
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
