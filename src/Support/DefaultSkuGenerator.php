<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Support;

use Karnoweb\Shop\Contracts\SkuGeneratorContract;

/**
 * Default SKU: `{pi-slug}` for the main product, `{pi-slug}-{id}-{id}` for variants.
 *
 * Value ids are sorted before joining so the same signature always yields the same SKU.
 */
final class DefaultSkuGenerator implements SkuGeneratorContract
{
    public function generate(string $productInterfaceSlug, array $valueIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $valueIds)));
        sort($ids);

        if ($ids === []) {
            return $productInterfaceSlug;
        }

        return $productInterfaceSlug.'-'.implode('-', $ids);
    }
}
