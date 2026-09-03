<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Facades;

use Illuminate\Support\Facades\Facade;
use Karnoweb\Shop\Shop as ShopManager;

/**
 * @method static mixed                                             config(string $key, mixed $default = null)
 * @method static \Karnoweb\Shop\Services\ProductService            products()
 * @method static \Karnoweb\Shop\Services\ProductFilterService      filters()
 * @method static \Karnoweb\Shop\Services\ProductPriceResolver      pricing()
 * @method static class-string<\Illuminate\Database\Eloquent\Model> model(string $key)
 * @method static \Illuminate\Database\Eloquent\Model               newModel(string $key)
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
