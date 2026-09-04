<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Karnoweb\Shop\DTOs\VariantPreviewResult;
use Karnoweb\Shop\DTOs\VariantSyncResult;
use Karnoweb\Shop\Services\VariantsService;

/**
 * Fluent coding-axis preview/sync for a product interface.
 *
 * @example
 * Shop::variants()
 *     ->forProductInterface($pi->id)
 *     ->codingAxes([
 *         $colorId => ['coding' => true, 'values' => [$red, $blue]],
 *         $sizeId => ['coding' => true, 'values' => [$s, $m]],
 *     ])
 *     ->preview();
 */
class VariantsBuilder
{
    private int|string|null $productInterfaceId = null;

    /** @var array<int|string, array{coding?: bool, values?: list<int|string>}> */
    private array $axes = [];

    public function __construct(
        private readonly VariantsService $variants,
    ) {}

    public function forProductInterface(int|string $productInterfaceId): self
    {
        $this->productInterfaceId = $productInterfaceId;

        return $this;
    }

    /**
     * @param  array<int|string, array{coding?: bool, values?: list<int|string>}>  $axes
     */
    public function codingAxes(array $axes): self
    {
        $this->axes = $axes;

        return $this;
    }

    public function preview(): VariantPreviewResult
    {
        return $this->variants->preview($this->requireInterfaceId(), $this->axes);
    }

    public function sync(string $mode = 'safe'): VariantSyncResult
    {
        return $this->variants->sync($this->requireInterfaceId(), $this->axes, $mode);
    }

    public function saveAxes(): string
    {
        return $this->variants->saveAxes($this->requireInterfaceId(), $this->axes);
    }

    private function requireInterfaceId(): int|string
    {
        if ($this->productInterfaceId === null) {
            throw new \InvalidArgumentException('Product interface id is required. Call forProductInterface() first.');
        }

        return $this->productInterfaceId;
    }
}
