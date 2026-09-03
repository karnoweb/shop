<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Karnoweb\Shop\Tests\TestCase;

/**
 * Proves the package is installable/testable/removable standalone (no host app,
 * no App\Models, no host `users`/`categories`/`user_groups` tables, no hard FK
 * into `karnoweb/commerce` tables such as orders/order_items/discounts): migrate
 * must succeed on a bare sqlite connection, and rollback must cleanly reverse it.
 */
final class MigrationsInstallStandaloneTest extends TestCase
{
    public function test_migrate_creates_expected_tables_without_any_host_or_commerce_tables(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        foreach ([
            'brands',
            'attribute_groups',
            'attributes',
            'attribute_values',
            'product_interfaces',
            'products',
            'campaigns',
            'product_prices',
            'user_wishlists',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist after migrate.");
        }

        // Never provided by this package — proves no hard dependency was created on them.
        foreach (['users', 'categories', 'user_groups', 'discounts', 'orders', 'order_items'] as $foreignTable) {
            $this->assertFalse(Schema::hasTable($foreignTable), "Host/cross-domain table [{$foreignTable}] must not be created by this package.");
        }
    }

    public function test_migrate_rollback_cleanly_reverses_every_migration(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);

        foreach ([
            'brands',
            'attribute_groups',
            'attributes',
            'attribute_values',
            'product_interfaces',
            'products',
            'campaigns',
            'product_prices',
            'user_wishlists',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Expected table [{$table}] to be dropped after rollback.");
        }
    }
}
