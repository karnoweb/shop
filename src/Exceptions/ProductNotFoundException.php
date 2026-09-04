<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown when a builder/service is given a product id that does not exist
 * on the model configured at `shop.models.product`.
 */
class ProductNotFoundException extends Exception
{
    public function __construct(
        public readonly int|string|null $productId = null,
        string $message = '',
        int $code = 404,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?: __('shop::shop.exceptions.product_not_found', ['id' => (string) $productId]),
            $code,
            $previous
        );
    }
}
