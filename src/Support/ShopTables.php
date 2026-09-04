<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Support;

use Karnoweb\Shop\Models\BaseModel;

/**
 * Resolve the physical table name for a package-owned table key.
 *
 * Same pattern as `karnoweb/laravel-inventory`: every table is prefixed with
 * `config('shop.general.prefix', 'shp_')` unless an exact override is
 * configured at `config('shop.tables.<key>')`. Used by both
 * {@see BaseModel} (Eloquent) and the squashed schema
 * migration / raw query builder calls, so prefix/overrides apply consistently
 * everywhere this package touches the database.
 *
 * @example
 * ShopTables::name('products'); // "shp_products" by default
 */
final class ShopTables
{
    /** The configured table prefix (default "shp_"). */
    public static function prefix(): string
    {
        return (string) config('shop.general.prefix', 'shp_');
    }

    /**
     * Resolve the physical table name for a given base key (e.g. "products",
     * "product_attribute_values"). Prefers an exact `shop.tables.<key>`
     * override; otherwise prepends the configured prefix. Never double-
     * prefixes a key that already starts with the current prefix.
     */
    public static function name(string $key): string
    {
        $configured = config("shop.tables.{$key}");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $prefix = self::prefix();

        if ($prefix === '' || str_starts_with($key, $prefix)) {
            return $key;
        }

        return $prefix.$key;
    }
}
