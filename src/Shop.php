<?php

declare(strict_types=1);

namespace Karnoweb\Shop;

use Illuminate\Support\Traits\Macroable;
use Karnoweb\Shop\Services\ProductFilterService;
use Karnoweb\Shop\Services\ProductPriceResolver;
use Karnoweb\Shop\Services\ProductService;
use Karnoweb\Shop\Support\ResolvesConfiguredModels;

/**
 * Thin manager: service delegation, config access, and host macros (CRM pattern + Macroable).
 */
class Shop
{
    use Macroable;
    use ResolvesConfiguredModels;

    public function __construct(
        private readonly ProductService $products,
        private readonly ProductFilterService $filters,
        private readonly ProductPriceResolver $pricing,
    ) {}

    public function config(string $key, mixed $default = null): mixed
    {
        return config("shop.{$key}", $default);
    }

    public function products(): ProductService
    {
        return $this->products;
    }

    public function filters(): ProductFilterService
    {
        return $this->filters;
    }

    public function pricing(): ProductPriceResolver
    {
        return $this->pricing;
    }
}
