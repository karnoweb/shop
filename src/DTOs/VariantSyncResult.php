<?php

declare(strict_types=1);

namespace Karnoweb\Shop\DTOs;

/**
 * Result of a coding-axis variant sync.
 */
final readonly class VariantSyncResult
{
    /**
     * @param  list<int|string>  $createdProductIds
     * @param  list<int|string>  $suspendedProductIds
     * @param  list<int|string>  $lockedProductIds
     */
    public function __construct(
        public int $productInterfaceId,
        public string $mode,
        public string $hash,
        public string $status,
        public int $created,
        public int $suspended,
        public int $unchanged,
        public int $skippedLocked,
        public array $createdProductIds,
        public array $suspendedProductIds,
        public array $lockedProductIds,
    ) {}
}
