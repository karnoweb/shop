<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Unit;

use Karnoweb\Shop\Support\ShopTables;
use Karnoweb\Shop\Tests\Feature\MigrationsInstallStandaloneTest;
use Karnoweb\Shop\Tests\TestCase;

/**
 * Pure config-resolution tests for {@see ShopTables} — no database needed.
 * DB-level proof that migrations actually create prefixed tables lives in
 * {@see MigrationsInstallStandaloneTest}.
 */
final class ShopTablesTest extends TestCase
{
    public function test_name_uses_configured_prefix_by_default(): void
    {
        config(['shop.general.prefix' => 'shp_']);

        $this->assertSame('shp_products', ShopTables::name('products'));
        $this->assertSame('shp_', ShopTables::prefix());
    }

    public function test_name_respects_empty_prefix(): void
    {
        config(['shop.general.prefix' => '']);

        $this->assertSame('products', ShopTables::name('products'));
    }

    public function test_name_prefers_exact_table_override(): void
    {
        config([
            'shop.general.prefix' => 'shp_',
            'shop.tables.products' => 'catalog_products',
        ]);

        $this->assertSame('catalog_products', ShopTables::name('products'));
    }

    public function test_name_falls_back_to_prefix_when_override_is_null_or_empty(): void
    {
        config([
            'shop.general.prefix' => 'shp_',
            'shop.tables.products' => null,
            'shop.tables.brands' => '',
        ]);

        $this->assertSame('shp_products', ShopTables::name('products'));
        $this->assertSame('shp_brands', ShopTables::name('brands'));
    }

    public function test_name_never_double_prefixes_a_key_that_already_has_the_prefix(): void
    {
        config(['shop.general.prefix' => 'shp_']);

        $this->assertSame('shp_products', ShopTables::name('shp_products'));
    }
}
