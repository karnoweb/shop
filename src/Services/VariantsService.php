<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Karnoweb\Shop\Contracts\SkuGeneratorContract;
use Karnoweb\Shop\DTOs\VariantPreviewResult;
use Karnoweb\Shop\DTOs\VariantSyncResult;
use Karnoweb\Shop\Enums\ProductInterfaceTypeEnum;
use Karnoweb\Shop\Enums\VariantsStatusEnum;
use Karnoweb\Shop\Events\ProductSaved;
use Karnoweb\Shop\Exceptions\ProductInterfaceNotFoundException;
use Karnoweb\Shop\Support\ShopEventDispatcher;
use Karnoweb\Shop\Support\ShopTables;

class VariantsService
{
    public function __construct(
        private readonly SkuGeneratorContract $skuGenerator,
    ) {}

    /**
     * @param  array<int|string, array{coding?: bool, values?: list<int|string>}>  $axes
     */
    public function preview(int|string $productInterfaceId, array $axes): VariantPreviewResult
    {
        $interface = $this->findInterface($productInterfaceId);
        $normalized = $this->normalizeAxes($axes);
        $variants = $this->buildPreviewRows($interface, $normalized);

        return new VariantPreviewResult(
            productInterfaceId: (int) $interface->getKey(),
            hash: $this->hashAxes($normalized),
            variants: $variants,
        );
    }

    /**
     * @param  array<int|string, array{coding?: bool, values?: list<int|string>}>  $axes
     */
    public function sync(int|string $productInterfaceId, array $axes, string $mode = 'safe'): VariantSyncResult
    {
        $mode = $mode === 'force' ? 'force' : 'safe';

        return DB::transaction(function () use ($productInterfaceId, $axes, $mode): VariantSyncResult {
            $interface = $this->findInterface($productInterfaceId);
            $normalized = $this->normalizeAxes($axes);
            $hash = $this->hashAxes($normalized);

            $this->persistAxes($interface, $normalized);

            $preview = $this->buildPreviewRows($interface, $normalized);
            $wanted = [];
            foreach ($preview as $row) {
                $wanted[$row['signature']] = $row;
            }

            /** @var class-string<Model> $productClass */
            $productClass = config('shop.models.product');

            $existing = $productClass::query()
                ->where('product_interface_id', $interface->getKey())
                ->where('is_main', false)
                ->get();

            $createdProductIds = [];
            $suspendedProductIds = [];
            $lockedProductIds = [];
            $created = 0;
            $suspended = 0;
            $unchanged = 0;
            $skippedLocked = 0;

            $seen = [];

            foreach ($existing as $product) {
                $signature = $this->signatureForProduct($product);

                if ($signature !== null && isset($wanted[$signature])) {
                    $seen[$signature] = true;

                    if ($product->getAttribute('locked_at') !== null) {
                        $lockedProductIds[] = $product->getKey();
                        $skippedLocked++;
                        $unchanged++;

                        continue;
                    }

                    if ($this->isSuspended($product) && $mode === 'force') {
                        $this->setSuspended($product, false);
                    }

                    $unchanged++;

                    continue;
                }

                if ($product->getAttribute('locked_at') !== null) {
                    $lockedProductIds[] = $product->getKey();
                    $skippedLocked++;

                    continue;
                }

                $this->setSuspended($product, true);
                $suspendedProductIds[] = $product->getKey();
                $suspended++;
            }

            foreach ($wanted as $signature => $row) {
                if (isset($seen[$signature])) {
                    continue;
                }

                $product = $productClass::query()->create([
                    'product_interface_id' => $interface->getKey(),
                    'is_main' => false,
                    'sku' => $row['sku'],
                    'branch_id' => $interface->getAttribute('branch_id'),
                    'published' => false,
                    'base_price' => 0,
                    'extra_attributes' => [
                        'variant_signature' => $signature,
                        'suspended' => false,
                    ],
                ]);

                $this->attachProductValues($product, $normalized, $row['value_ids']);

                ShopEventDispatcher::dispatch(new ProductSaved(
                    productId: $product->getKey(),
                    productInterfaceId: $interface->getKey(),
                ));

                $createdProductIds[] = $product->getKey();
                $created++;
            }

            $interface->forceFill([
                'variants_status' => VariantsStatusEnum::READY->value,
                'variants_hash' => $hash,
            ])->save();

            return new VariantSyncResult(
                productInterfaceId: (int) $interface->getKey(),
                mode: $mode,
                hash: $hash,
                status: VariantsStatusEnum::READY->value,
                created: $created,
                suspended: $suspended,
                unchanged: $unchanged,
                skippedLocked: $skippedLocked,
                createdProductIds: $createdProductIds,
                suspendedProductIds: $suspendedProductIds,
                lockedProductIds: $lockedProductIds,
            );
        });
    }

    /**
     * Persist axis selection and mark the interface as needing a variant sync when the hash changed.
     *
     * @param  array<int|string, array{coding?: bool, values?: list<int|string>}>  $axes
     */
    public function saveAxes(int|string $productInterfaceId, array $axes): string
    {
        $interface = $this->findInterface($productInterfaceId);
        $normalized = $this->normalizeAxes($axes);
        $hash = $this->hashAxes($normalized);

        $this->persistAxes($interface, $normalized);

        if ($interface->getAttribute('variants_hash') !== $hash) {
            $interface->forceFill([
                'variants_status' => VariantsStatusEnum::NEEDS_SYNC->value,
            ])->save();
        }

        return $hash;
    }

    private function findInterface(int|string $productInterfaceId): Model
    {
        /** @var class-string<Model> $class */
        $class = config('shop.models.product_interface');

        $interface = $class::query()->find($productInterfaceId);

        if ($interface === null) {
            throw new ProductInterfaceNotFoundException($productInterfaceId);
        }

        return $interface;
    }

    /**
     * @param  array<int|string, array{coding?: bool, values?: list<int|string>}>  $axes
     * @return array<int, array{coding: bool, values: list<int>}>
     */
    private function normalizeAxes(array $axes): array
    {
        $normalized = [];

        foreach ($axes as $attributeId => $definition) {
            $values = array_values(array_unique(array_map('intval', $definition['values'] ?? [])));
            sort($values);
            $normalized[(int) $attributeId] = [
                'coding' => (bool) ($definition['coding'] ?? false),
                'values' => $values,
            ];
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, array{coding: bool, values: list<int>}>  $axes
     */
    public function hashAxes(array $axes): string
    {
        return hash('sha256', json_encode($axes, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, array{coding: bool, values: list<int>}>  $axes
     * @return list<array{value_ids: list<int>, signature: string, sku: string}>
     */
    private function buildPreviewRows(Model $interface, array $axes): array
    {
        $codingSets = [];

        foreach ($axes as $definition) {
            if (! $definition['coding'] || $definition['values'] === []) {
                continue;
            }

            $codingSets[] = $definition['values'];
        }

        $combinations = $this->cartesian($codingSets);
        $slug = (string) $interface->getAttribute('slug');
        $rows = [];

        foreach ($combinations as $valueIds) {
            $signature = $this->signature($valueIds);
            $rows[] = [
                'value_ids' => $valueIds,
                'signature' => $signature,
                'sku' => $this->skuGenerator->generate($slug, $valueIds),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<list<int>>  $sets
     * @return list<list<int>>
     */
    private function cartesian(array $sets): array
    {
        if ($sets === []) {
            return [];
        }

        $result = [[]];

        foreach ($sets as $set) {
            $append = [];

            foreach ($result as $product) {
                foreach ($set as $item) {
                    $combination = $product;
                    $combination[] = $item;
                    $append[] = $combination;
                }
            }

            $result = $append;
        }

        return array_map(function (array $valueIds): array {
            sort($valueIds);

            return array_values($valueIds);
        }, $result);
    }

    /**
     * @param  list<int>  $valueIds
     */
    private function signature(array $valueIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $valueIds)));
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * @param  array<int, array{coding: bool, values: list<int>}>  $axes
     */
    private function persistAxes(Model $interface, array $axes): void
    {
        $interfaceId = $interface->getKey();
        $type = $interface->getAttribute('type');
        $typeValue = $type instanceof ProductInterfaceTypeEnum ? $type->value : (string) $type;
        $isSimple = $typeValue === ProductInterfaceTypeEnum::SIMPLE->value;

        $attributesTable = ShopTables::name('product_interface_attributes');
        $valuesTable = ShopTables::name('product_interface_attribute_values');

        DB::table($attributesTable)->where('product_interface_id', $interfaceId)->delete();
        DB::table($valuesTable)->where('product_interface_id', $interfaceId)->delete();

        foreach ($axes as $attributeId => $definition) {
            DB::table($attributesTable)->insert([
                'product_interface_id' => $interfaceId,
                'attribute_id' => $attributeId,
                'codding' => $definition['coding'],
            ]);

            $storeOnInterface = $isSimple || ! $definition['coding'];

            if (! $storeOnInterface) {
                continue;
            }

            if ($isSimple && count($definition['values']) > 1) {
                $definition['values'] = array_slice($definition['values'], 0, 1);
            }

            foreach ($definition['values'] as $valueId) {
                DB::table($valuesTable)->insert([
                    'product_interface_id' => $interfaceId,
                    'attribute_id' => $attributeId,
                    'attribute_value_id' => $valueId,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{coding: bool, values: list<int>}>  $axes
     * @param  list<int>  $valueIds
     */
    private function attachProductValues(Model $product, array $axes, array $valueIds): void
    {
        $table = ShopTables::name('product_attribute_values');
        $valueToAttribute = [];

        foreach ($axes as $attributeId => $definition) {
            if (! $definition['coding']) {
                continue;
            }

            foreach ($definition['values'] as $valueId) {
                $valueToAttribute[$valueId] = $attributeId;
            }
        }

        foreach ($valueIds as $valueId) {
            $attributeId = $valueToAttribute[$valueId] ?? null;

            if ($attributeId === null) {
                continue;
            }

            DB::table($table)->insert([
                'product_id' => $product->getKey(),
                'attribute_id' => $attributeId,
                'attribute_value_id' => $valueId,
            ]);
        }
    }

    private function signatureForProduct(Model $product): ?string
    {
        $stored = data_get($product->getAttribute('extra_attributes'), 'variant_signature');

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $ids = DB::table(ShopTables::name('product_attribute_values'))
            ->where('product_id', $product->getKey())
            ->pluck('attribute_value_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return null;
        }

        return $this->signature($ids);
    }

    private function isSuspended(Model $product): bool
    {
        return (bool) data_get($product->getAttribute('extra_attributes'), 'suspended', false);
    }

    private function setSuspended(Model $product, bool $suspended): void
    {
        $extra = $product->getAttribute('extra_attributes') ?? [];
        $extra['suspended'] = $suspended;
        $product->forceFill(['extra_attributes' => $extra])->save();
    }
}
