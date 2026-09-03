<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Events;

final readonly class ProductSaved
{
    public function __construct(
        public int|string $productId,
        public int|string $productInterfaceId,
    ) {}
}
