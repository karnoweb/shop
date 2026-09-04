<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown when a ProductPrice amount is negative.
 */
class InvalidPriceAmountException extends Exception
{
    public function __construct(
        public readonly int|float|null $amount = null,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?: __('shop::shop.exceptions.invalid_price_amount'),
            $code,
            $previous
        );
    }
}
