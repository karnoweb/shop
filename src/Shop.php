<?php

declare(strict_types=1);

namespace Karnoweb\Shop;

use Illuminate\Support\Traits\Macroable;
use Karnoweb\Shop\Builders\BrandBuilder;
use Karnoweb\Shop\Builders\BulkProductPriceBuilder;
use Karnoweb\Shop\Builders\ProductBuilder;
use Karnoweb\Shop\Builders\ProductInterfaceBuilder;
use Karnoweb\Shop\Builders\ProductPriceBuilder;
use Karnoweb\Shop\Builders\QuoteBuilder;
use Karnoweb\Shop\Builders\VariantsBuilder;
use Karnoweb\Shop\Services\ProductFilterService;
use Karnoweb\Shop\Services\ProductPriceResolver;
use Karnoweb\Shop\Services\ProductService;
use Karnoweb\Shop\Services\QuoteService;
use Karnoweb\Shop\Services\VariantsService;
use Karnoweb\Shop\Support\ResolvesConfiguredModels;
use Karnoweb\Shop\Support\ShopContext;

/**
 * Thin manager: service delegation, config access, builder entry points, and
 * host macros (CRM/Accounting pattern + Macroable).
 */
class Shop
{
    use Macroable;
    use ResolvesConfiguredModels;

    public function __construct(
        private readonly ProductService $products,
        private readonly ProductFilterService $filters,
        private readonly ProductPriceResolver $pricing,
        private readonly QuoteService $quoteService,
        private readonly VariantsService $variantsService,
        private readonly ShopContext $context,
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

    public function context(): ShopContext
    {
        return $this->context;
    }

    /** Start building a new Brand (fluent API). Fresh builder each call. */
    public function brand(): BrandBuilder
    {
        return new BrandBuilder;
    }

    /** Start building a new ProductInterface (fluent API). Fresh builder each call. */
    public function productInterface(): ProductInterfaceBuilder
    {
        return new ProductInterfaceBuilder;
    }

    /** Start building a new Product (fluent API). Fresh builder each call. */
    public function product(): ProductBuilder
    {
        return new ProductBuilder;
    }

    /** Start writing a new time-windowed ProductPrice (fluent API). Fresh builder each call. */
    public function price(): ProductPriceBuilder
    {
        return new ProductPriceBuilder;
    }

    /** Start writing prices for many products at once. Fresh builder each call. */
    public function prices(): BulkProductPriceBuilder
    {
        return new BulkProductPriceBuilder;
    }

    /** Start a coding-axis preview/sync. Fresh builder each call. */
    public function variants(): VariantsBuilder
    {
        return new VariantsBuilder($this->variantsService);
    }

    /** Start resolving a portable PriceQuote DTO (fluent API). Fresh builder each call. */
    public function quote(): QuoteBuilder
    {
        return new QuoteBuilder($this->quoteService);
    }

    /** Get the quote service directly (e.g. for resolving without the builder). */
    public function quotes(): QuoteService
    {
        return $this->quoteService;
    }
}
