<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Contracts;

use Karnoweb\Shop\Support\DefaultSkuGenerator;

/**
 * Stable SKU generator for a product-interface slug + variant signature.
 *
 * Bind a host implementation to override {@see DefaultSkuGenerator}.
 */
interface SkuGeneratorContract
{
    /**
     * @param  list<int|string>  $valueIds  Sorted coding-axis value ids (empty for the main product).
     */
    public function generate(string $productInterfaceSlug, array $valueIds): string;
}
