<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Exceptions;

use Exception;
use Throwable;

class ProductInterfaceNotFoundException extends Exception
{
    public function __construct(
        public readonly int|string|null $productInterfaceId = null,
        string $message = '',
        int $code = 404,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?: __('shop::shop.exceptions.product_interface_not_found', ['id' => (string) $productInterfaceId]),
            $code,
            $previous
        );
    }
}
