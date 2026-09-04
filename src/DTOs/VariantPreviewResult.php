<?php

declare(strict_types=1);

namespace Karnoweb\Shop\DTOs;

/**
 * Cartesian preview of coding-axis variants. No database writes.
 */
final readonly class VariantPreviewResult
{
    /**
     * @param  list<array{value_ids: list<int>, signature: string, sku: string}>  $variants
     */
    public function __construct(
        public int $productInterfaceId,
        public string $hash,
        public array $variants,
    ) {}

    public function count(): int
    {
        return count($this->variants);
    }
}
