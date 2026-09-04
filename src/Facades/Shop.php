<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Facades;

use Illuminate\Support\Facades\Facade;
use Karnoweb\Shop\Shop as ShopManager;

/**
 * @method static mixed config(string $key, mixed $default = null)
 * @method static \Karnoweb\Shop\Services\ProductService products()
 * @method static \Karnoweb\Shop\Services\ProductFilterService filters()
 * @method static \Karnoweb\Shop\Services\ProductPriceResolver pricing()
 * @method static \Karnoweb\Shop\Support\ShopContext context()
 * @method static class-string<\Illuminate\Database\Eloquent\Model> model(string $key)
 * @method static \Illuminate\Database\Eloquent\Model newModel(string $key)
 * @method static \Karnoweb\Shop\Builders\BrandBuilder brand() Start building a new Brand (fluent API).
 * @method static \Karnoweb\Shop\Builders\ProductInterfaceBuilder productInterface() Start building a new ProductInterface (fluent API).
 * @method static \Karnoweb\Shop\Builders\ProductBuilder product() Start building a new Product (fluent API).
 * @method static \Karnoweb\Shop\Builders\ProductPriceBuilder price() Start writing a time-windowed ProductPrice (fluent API).
 * @method static \Karnoweb\Shop\Builders\BulkProductPriceBuilder prices() Start writing prices for many products (fluent API).
 * @method static \Karnoweb\Shop\Builders\VariantsBuilder variants() Start a coding-axis preview/sync (fluent API).
 * @method static \Karnoweb\Shop\Builders\QuoteBuilder quote() Start resolving a portable PriceQuote DTO (fluent API).
 * @method static \Karnoweb\Shop\Services\QuoteService quotes() Get the quote service directly.
 *
 * @see ShopManager
 */
final class Shop extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'shop';
    }
}
