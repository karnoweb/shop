<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown when a ProductPrice window is invalid, i.e. `starts_at` is after `ends_at`.
 */
class InvalidPriceWindowException extends Exception
{
    public function __construct(
        public readonly ?string $startsAt = null,
        public readonly ?string $endsAt = null,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?: __('shop::shop.exceptions.invalid_price_window'),
            $code,
            $previous
        );
    }
}
