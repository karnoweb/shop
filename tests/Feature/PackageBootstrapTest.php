<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Shop\Facades\Shop as ShopFacade;
use Karnoweb\Shop\Models\BaseModel;
use Karnoweb\Shop\Models\Brand;
use Karnoweb\Shop\Services\ProductFilterService;
use Karnoweb\Shop\Services\ProductPriceResolver;
use Karnoweb\Shop\Services\ProductService;
use Karnoweb\Shop\Shop;
use Karnoweb\Shop\ShopServiceProvider;
use Karnoweb\Shop\Tests\TestCase;

/**
 * No defineDatabaseMigrations() override here — {@see ShopServiceProvider::boot()}
 * already registers the real squashed schema (database/migrations_squashed) for
 * every test via RefreshDatabase, so this suite exercises the actual package
 * schema rather than a parallel test-only fixture.
 */
final class PackageBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(ShopServiceProvider::class));
    }

    public function test_shop_singleton_and_facade_resolve(): void
    {
        $this->assertTrue($this->app->bound('shop'));
        $this->assertSame($this->app->make('shop'), $this->app->make('shop'));
        $this->assertInstanceOf(Shop::class, ShopFacade::getFacadeRoot());
        $this->assertSame('', ShopFacade::config('general.prefix'));
    }

    public function test_shop_facade_exposes_services(): void
    {
        $this->assertInstanceOf(ProductService::class, ShopFacade::products());
        $this->assertInstanceOf(ProductFilterService::class, ShopFacade::filters());
        $this->assertInstanceOf(ProductPriceResolver::class, ShopFacade::pricing());
    }

    public function test_brands_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('brands'));
        $this->assertTrue(Schema::hasColumns('brands', [
            'slug',
            'published',
            'ordering',
            'view_count',
        ]));
    }

    public function test_translations_are_loaded(): void
    {
        $this->assertSame('Text', __('shop::shop.attribute.type.text'));
    }

    public function test_base_model_respects_empty_prefix(): void
    {
        $model = new class extends BaseModel
        {
            protected $table = 'brands';
        };

        $this->assertSame('brands', $model->getTable());
    }

    public function test_brand_model_scopes(): void
    {
        $this->makeBrand(['published' => true]);
        $this->makeBrand(['published' => false]);

        $this->assertSame(1, Brand::query()->active()->count());
    }

    public function test_shop_model_resolver(): void
    {
        config(['shop.models.brand' => Brand::class]);

        $this->assertSame(Brand::class, ShopFacade::model('brand'));
    }

    public function test_shop_supports_macros(): void
    {
        ShopFacade::macro('testMacro', fn (): string => 'ok');

        $this->assertSame('ok', ShopFacade::testMacro());
    }

    public function test_products_table_has_current_schema(): void
    {
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasColumns('products', [
            'is_main',
            'base_price',
            'product_interface_id',
            'branch_id',
            'weight_grams',
            'locked_at',
        ]));
    }
}
