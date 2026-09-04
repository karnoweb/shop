<?php

declare(strict_types=1);

namespace Karnoweb\Shop;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Shop\Builders\QuoteBuilder;
use Karnoweb\Shop\Contracts\CampaignPriceAdjuster;
use Karnoweb\Shop\Contracts\StorefrontContext;
use Karnoweb\Shop\Services\ProductFilterService;
use Karnoweb\Shop\Services\ProductPriceResolver;
use Karnoweb\Shop\Services\ProductService;
use Karnoweb\Shop\Services\QuoteService;
use Karnoweb\Shop\Support\ShopMorphMap;

class ShopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shop.php', 'shop');

        $this->app->singleton('shop', fn ($app) => new Shop(
            $app->make(ProductService::class),
            $app->make(ProductFilterService::class),
            $app->make(ProductPriceResolver::class),
            $app->make(QuoteService::class),
        ));
        $this->app->singleton(Shop::class, fn ($app) => $app->make('shop'));

        $this->app->singleton(ProductPriceResolver::class);
        $this->app->singleton(ProductFilterService::class);
        $this->app->singleton(ProductService::class, function ($app) {
            return new ProductService(
                $app->make(ProductPriceResolver::class),
                $app->bound(CampaignPriceAdjuster::class) ? $app->make(CampaignPriceAdjuster::class) : null,
                $app->bound(StorefrontContext::class) ? $app->make(StorefrontContext::class) : null,
            );
        });

        $this->app->singleton(QuoteService::class, function ($app) {
            return new QuoteService(
                $app->make(ProductPriceResolver::class),
                $app->bound(CampaignPriceAdjuster::class) ? $app->make(CampaignPriceAdjuster::class) : null,
            );
        });

        // Transient: each resolve is a fresh builder (no shared quote state).
        $this->app->bind(QuoteBuilder::class, function ($app) {
            return new QuoteBuilder($app->make(QuoteService::class));
        });
    }

    public function boot(): void
    {
        ShopMorphMap::register();

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'shop');

        // Single squashed schema migration — see database/migrations_squashed.
        // database/migrations_legacy is kept only as historical reference and
        // is never loaded.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations_squashed');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/shop.php' => config_path('shop.php'),
            ], 'shop-config');

            $this->publishes([
                __DIR__.'/../database/migrations_squashed' => database_path('migrations'),
            ], 'shop-migrations');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/shop'),
            ], 'shop-lang');
        }
    }
}
