<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Shop\ShopServiceProvider;
use Karnoweb\Shop\Tests\TestCase;

/**
 * Proves the package is installable/testable/removable standalone (no host app,
 * no App\Models, no host `users`/`categories`/`user_groups` tables, no hard FK
 * into `karnoweb/commerce` tables such as orders/order_items/discounts): migrate
 * must succeed on a bare sqlite connection, and rollback must cleanly reverse it.
 *
 * Also proves the schema is squashed to a single migration file — see
 * `database/migrations_squashed` (the only path {@see ShopServiceProvider}
 * loads; `database/migrations_legacy` is kept as reference only, never loaded).
 */
final class MigrationsInstallStandaloneTest extends TestCase
{
    public function test_only_one_schema_migration_is_loaded(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        // Scope to this package's own migrations only — karnoweb/translation
        // (a required dependency, also registered in getPackageProviders())
        // legitimately contributes its own separate migration.
        $migrations = DB::table('migrations')
            ->pluck('migration')
            ->filter(fn (string $name): bool => ! str_contains($name, 'create_translations_table'))
            ->values()
            ->all();

        $this->assertSame(
            ['2026_09_04_000000_create_shop_schema'],
            $migrations,
            'Expected exactly one squashed shop schema migration to run, got: '.implode(', ', $migrations)
        );
    }

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

        $this->assertTrue(Schema::hasColumns('product_interfaces', ['kind', 'extra_attributes']));
        $this->assertTrue(Schema::hasColumns('products', ['extra_attributes', 'default_uom_code']));
        $this->assertTrue(Schema::hasColumn('brands', 'extra_attributes'));

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
