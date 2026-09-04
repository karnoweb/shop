<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Support;

final class Money
{
    public static function defaultCurrency(): string
    {
        return (string) config('shop.money.default_currency', config('shop.pricing.currency', 'IRR'));
    }
}
