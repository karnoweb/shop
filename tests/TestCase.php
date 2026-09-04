<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests;

use Karnoweb\Shop\Models\Attribute;
use Karnoweb\Shop\Models\AttributeGroup;
use Karnoweb\Shop\Models\AttributeValue;
use Karnoweb\Shop\Models\Brand;
use Karnoweb\Shop\Models\Campaign;
use Karnoweb\Shop\Models\Product;
use Karnoweb\Shop\Models\ProductInterface;
use Karnoweb\Shop\Models\ProductPrice;
use Karnoweb\Shop\ShopServiceProvider;
use Karnoweb\Translation\TranslationServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TranslationServiceProvider::class,
            ShopServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        // Empty prefix keeps the bulk of the test suite working with plain
        // (unprefixed) table names; dedicated prefix tests override this at
        // runtime to prove the default ("shp_") and custom prefixes work.
        $app['config']->set('shop.general.prefix', '');
        $app['config']->set('shop.models.brand', Brand::class);
        $app['config']->set('shop.models.product_interface', ProductInterface::class);
        $app['config']->set('shop.models.product', Product::class);
        $app['config']->set('shop.models.product_price', ProductPrice::class);
        $app['config']->set('shop.models.attribute', Attribute::class);
        $app['config']->set('shop.models.attribute_group', AttributeGroup::class);
        $app['config']->set('shop.models.attribute_value', AttributeValue::class);
        $app['config']->set('shop.models.campaign', Campaign::class);
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
    }

    protected function makeBrand(array $attributes = []): Brand
    {
        return Brand::query()->create(array_merge([
            'slug' => 'brand-'.uniqid(),
            'published' => true,
            'ordering' => 1,
            'view_count' => 0,
            'languages' => ['en'],
        ], $attributes));
    }
}
