<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests;

use Karnoweb\Shop\Models\Brand;
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
        $app['config']->set('shop.tables.prefix', '');
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
    }

    protected function makeBrand(array $attributes = []): Brand
    {
        return Brand::query()->create(array_merge([
            'slug' => 'brand-' . uniqid(),
            'published' => true,
            'ordering' => 1,
            'view_count' => 0,
            'languages' => ['en'],
        ], $attributes));
    }
}
