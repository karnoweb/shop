<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Shop\ShopServiceProvider;
use Karnoweb\Shop\Support\ShopTables;
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
    private const BASE_TABLE_KEYS = [
        'brands',
        'attribute_groups',
        'attributes',
        'attribute_values',
        'product_interfaces',
        'products',
        'campaigns',
        'product_prices',
    ];

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

        foreach (self::BASE_TABLE_KEYS as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist after migrate.");
        }

        // Removed entirely — wishlist/cart/compare/rating session state is a
        // host concern behind StorefrontContext, not a package-owned table.
        $this->assertFalse(Schema::hasTable('user_wishlists'), 'user_wishlists must not be created by this package.');

        $this->assertTrue(Schema::hasColumns('product_interfaces', ['kind', 'extra_attributes', 'branch_id', 'variants_status', 'variants_hash']));
        $this->assertTrue(Schema::hasColumn('product_interfaces', 'category_id'));
        $this->assertTrue(Schema::hasColumns('products', ['extra_attributes', 'default_uom_code', 'branch_id', 'weight_grams', 'locked_at', 'locked_reason', 'locked_by']));
        $this->assertFalse(Schema::hasColumn('products', 'weight'), 'weight was replaced by weight_grams.');
        $this->assertFalse(Schema::hasColumn('products', 'height'), 'height moved to extra_attributes.');
        $this->assertFalse(Schema::hasColumn('products', 'length'), 'length moved to extra_attributes.');
        $this->assertFalse(Schema::hasColumn('products', 'width'), 'width moved to extra_attributes.');
        $this->assertTrue(Schema::hasColumn('brands', 'extra_attributes'));
        $this->assertTrue(Schema::hasColumns('product_prices', ['segment_id', 'branch_id', 'currency']));
        $this->assertFalse(Schema::hasColumn('product_prices', 'user_group_id'), 'user_group_id was renamed to segment_id.');
        $this->assertTrue(Schema::hasColumns('campaigns', ['external_discount_id', 'branch_id', 'payload']));
        $this->assertFalse(Schema::hasColumn('campaigns', 'discount_id'), 'discount_id was renamed to external_discount_id.');

        // Never provided by this package — proves no hard dependency was created on them.
        foreach (['users', 'categories', 'user_groups', 'discounts', 'orders', 'order_items'] as $foreignTable) {
            $this->assertFalse(Schema::hasTable($foreignTable), "Host/cross-domain table [{$foreignTable}] must not be created by this package.");
        }
    }

    public function test_migrate_rollback_cleanly_reverses_every_migration(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);

        foreach (self::BASE_TABLE_KEYS as $table) {
            $this->assertFalse(Schema::hasTable($table), "Expected table [{$table}] to be dropped after rollback.");
        }
    }

    public function test_migrate_uses_default_shp_prefix_when_not_overridden(): void
    {
        // TestCase forces an empty prefix for the rest of the suite's
        // convenience; this test restores the package's real default to
        // prove it is actually "shp_" out of the box.
        config(['shop.general.prefix' => 'shp_']);

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        foreach (self::BASE_TABLE_KEYS as $key) {
            $this->assertTrue(
                Schema::hasTable("shp_{$key}"),
                "Expected prefixed table [shp_{$key}] to exist after migrate with the default prefix."
            );
            $this->assertFalse(
                Schema::hasTable($key),
                "Unprefixed table [{$key}] should not exist when the default prefix is active."
            );
        }

        $this->assertSame('shp_products', ShopTables::name('products'));
    }

    public function test_migrate_and_rollback_use_a_custom_configured_prefix(): void
    {
        config(['shop.general.prefix' => 'acme_']);

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        foreach (self::BASE_TABLE_KEYS as $key) {
            $this->assertTrue(Schema::hasTable("acme_{$key}"), "Expected custom-prefixed table [acme_{$key}] to exist.");
        }

        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);

        foreach (self::BASE_TABLE_KEYS as $key) {
            $this->assertFalse(Schema::hasTable("acme_{$key}"), "Expected custom-prefixed table [acme_{$key}] to be dropped after rollback.");
        }
    }

    public function test_migrate_uses_an_exact_table_name_override(): void
    {
        config(['shop.tables.brands' => 'catalog_brands']);

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('catalog_brands'));
        $this->assertFalse(Schema::hasTable('brands'));
        $this->assertFalse(Schema::hasTable('shp_brands'));
    }
}
