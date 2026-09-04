<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Karnoweb\Shop\Support\ShopTables;

class ProductFilterService
{
    private ?Collection $categoryTree = null;

    private ?array $childMap = null;

    /**
     * @return class-string<Model>
     */
    private function productModel(): string
    {
        return config('shop.models.product');
    }

    /**
     * @return class-string<Model>
     */
    private function brandModel(): string
    {
        return config('shop.models.brand');
    }

    /**
     * @return class-string<Model>
     */
    private function attributeModel(): string
    {
        return config('shop.models.attribute');
    }

    /**
     * @return class-string<Model>
     */
    private function attributeValueModel(): string
    {
        return config('shop.models.attribute_value');
    }

    /**
     * @return class-string<Model>
     */
    private function categoryModel(): string
    {
        return config('shop.models.category');
    }

    private function publishedEnabled(): int|bool
    {
        return config('shop.published_enabled_value', 1);
    }

    private function categoryProductType(): string
    {
        return (string) config('shop.category_product_type', 'product');
    }

    public function getCategoryTree(): Collection
    {
        $categoryClass = $this->categoryModel();

        return $this->categoryTree ??= Cache::remember(
            'pf:cat_tree',
            3600,
            fn () => $categoryClass::query()
                ->where('type', $this->categoryProductType())
                ->where('categories.published', $this->publishedEnabled())
                ->orderBy('ordering')
                ->get(['id', 'parent_id', 'slug', 'ordering'])
        );
    }

    private function childMap(): array
    {
        return $this->childMap ??= $this->getCategoryTree()
            ->groupBy(fn ($c) => $c->parent_id ?? 0)
            ->map(fn (Collection $cats) => $cats->pluck('id')->all())
            ->all();
    }

    /**
     * @return list<int>
     */
    public function descendantIds(int $categoryId): array
    {
        $map = $this->childMap();
        $ids = [$categoryId];
        $queue = [$categoryId];

        while ($queue) {
            $current = array_shift($queue);
            foreach ($map[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    public function hasChildren(int $categoryId): bool
    {
        return ! empty($this->childMap()[$categoryId] ?? []);
    }

    public function findCategory(int $id): ?object
    {
        return $this->getCategoryTree()->firstWhere('id', $id);
    }

    /**
     * @return array<int, int>
     */
    public function productCountsPerCategory(array $brandIds = [], array $attributeValueIds = []): array
    {
        $productClass = $this->productModel();
        $productsTable = (new $productClass)->getTable();
        $interfacesTable = ShopTables::name('product_interfaces');

        $query = $productClass::query()
            ->where("{$productsTable}.published", $this->publishedEnabled())
            ->whereNull("{$productsTable}.deleted_at")
            ->join("{$interfacesTable} as pi", "{$productsTable}.product_interface_id", '=', 'pi.id')
            ->where('pi.published', $this->publishedEnabled())
            ->whereNull('pi.deleted_at');

        if ($brandIds) {
            $query->whereIn('pi.brand_id', $brandIds);
        }

        if ($attributeValueIds) {
            $this->applyAttributeFilterJoined($query, $attributeValueIds);
        }

        return $query
            ->select('pi.category_id', DB::raw("COUNT(DISTINCT {$productsTable}.id) as cnt"))
            ->groupBy('pi.category_id')
            ->pluck('cnt', 'pi.category_id')
            ->all();
    }

    public function totalCountFor(int $categoryId, array $countsPerLeaf): int
    {
        $total = 0;
        foreach ($this->descendantIds($categoryId) as $id) {
            $total += $countsPerLeaf[$id] ?? 0;
        }

        return $total;
    }

    public function buildCategorySection(?int $parentId, array $countsPerLeaf): Collection
    {
        return $this->getCategoryTree()
            ->where('parent_id', $parentId)
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'title' => $cat->title,
                'slug' => $cat->slug,
                'count' => $this->totalCountFor($cat->id, $countsPerLeaf),
                'parent_id' => $cat->parent_id,
            ])
            ->filter(fn ($c) => $c['count'] > 0)
            ->values();
    }

    public function brandsWithCounts(): Collection
    {
        $brandClass = $this->brandModel();
        $brandsTable = (new $brandClass)->getTable();
        $productsTable = (new ($this->productModel()))->getTable();
        $interfacesTable = ShopTables::name('product_interfaces');

        return Cache::remember(
            'pf:brands',
            3600,
            fn () => $brandClass::query()
                ->where("{$brandsTable}.published", $this->publishedEnabled())
                ->join("{$interfacesTable} as pi", 'pi.brand_id', '=', "{$brandsTable}.id")
                ->where('pi.published', $this->publishedEnabled())
                ->whereNull('pi.deleted_at')
                ->join($productsTable, "{$productsTable}.product_interface_id", '=', 'pi.id')
                ->where("{$productsTable}.published", $this->publishedEnabled())
                ->whereNull("{$productsTable}.deleted_at")
                ->select("{$brandsTable}.id", "{$brandsTable}.slug", DB::raw("COUNT(DISTINCT {$productsTable}.id) as count"))
                ->groupBy("{$brandsTable}.id", "{$brandsTable}.slug")
                ->orderBy("{$brandsTable}.ordering")
                ->having('count', '>', 0)
                ->get()
                ->map(fn ($b) => ['id' => $b->id, 'title' => $b->title, 'count' => $b->count])
        );
    }

    /**
     * @return array<int, list<int>>
     */
    public function groupValuesByAttribute(array $valueIds): array
    {
        if ($valueIds === []) {
            return [];
        }

        $attributeValueClass = $this->attributeValueModel();

        return $attributeValueClass::query()
            ->whereIn('id', $valueIds)
            ->get(['id', 'attribute_id'])
            ->groupBy('attribute_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();
    }

    public function applyAttributeFilter(Builder $query, array $valueIds): void
    {
        $attributeValuesTable = (new ($this->attributeValueModel()))->getTable();

        foreach ($this->groupValuesByAttribute($valueIds) as $ids) {
            $query->where(
                fn ($q) => $q
                    ->whereHas('attributeValues', fn ($av) => $av->whereIn("{$attributeValuesTable}.id", $ids))
                    ->orWhereHas('productInterface.attributeValues', fn ($av) => $av->whereIn("{$attributeValuesTable}.id", $ids))
            );
        }
    }

    private function applyAttributeFilterJoined(Builder $query, array $valueIds): void
    {
        $productsTable = (new ($this->productModel()))->getTable();
        $pavTable = ShopTables::name('product_attribute_values');
        $piavTable = ShopTables::name('product_interface_attribute_values');

        foreach ($this->groupValuesByAttribute($valueIds) as $ids) {
            $query->where(
                fn ($q) => $q
                    ->whereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from("{$pavTable} as pav")
                        ->whereColumn('pav.product_id', "{$productsTable}.id")
                        ->whereIn('pav.attribute_value_id', $ids))
                    ->orWhereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from("{$piavTable} as piav")
                        ->whereColumn('piav.product_interface_id', 'pi.id')
                        ->whereIn('piav.attribute_value_id', $ids))
            );
        }
    }

    public function filterableAttributes(array $categoryIds): Collection
    {
        if ($categoryIds === []) {
            return collect();
        }

        $attributeIds = $this->attributeIdsForCategories($categoryIds);

        if ($attributeIds === []) {
            return collect();
        }

        $attributeClass = $this->attributeModel();

        $attributes = $attributeClass::query()
            ->where('filterable', $this->publishedEnabled())
            ->whereIn('id', $attributeIds)
            ->with(['values' => fn ($q) => $q->orderBy('order')])
            ->orderBy('order')
            ->get();

        $available = $this->availableValueIds($categoryIds, $attributeIds);

        return $attributes
            ->map(function ($attr) use ($available) {
                $ids = $available[$attr->id] ?? [];
                $values = $attr->values->whereIn('id', $ids);

                return $values->isEmpty() ? null : [
                    'id' => $attr->id,
                    'title' => $attr->title,
                    'type' => $attr->type->value,
                    'values' => $values->map(fn ($v) => [
                        'id' => $v->id,
                        'title' => $v->title,
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<int|string>
     */
    private function attributeIdsForCategories(array $categoryIds): array
    {
        $categoryAttributeGroupTable = ShopTables::name('category_attribute_group');
        $attributeAttributeGroupTable = ShopTables::name('attribute_attribute_group');
        $interfacesTable = ShopTables::name('product_interfaces');
        $piavTable = ShopTables::name('product_interface_attribute_values');
        $productsTable = (new ($this->productModel()))->getTable();
        $pavTable = ShopTables::name('product_attribute_values');

        $fromGroups = DB::table("{$categoryAttributeGroupTable} as cag")
            ->join("{$attributeAttributeGroupTable} as aag", 'cag.attribute_group_id', '=', 'aag.attribute_group_id')
            ->whereIn('cag.category_id', $categoryIds)
            ->select('aag.attribute_id as attr_id');

        $fromInterfaceValues = DB::table("{$interfacesTable} as pi")
            ->join("{$piavTable} as piav", 'pi.id', '=', 'piav.product_interface_id')
            ->whereIn('pi.category_id', $categoryIds)
            ->whereNull('pi.deleted_at')
            ->select('piav.attribute_id as attr_id');

        $fromProductValues = DB::table("{$productsTable} as p")
            ->join("{$interfacesTable} as pi2", 'p.product_interface_id', '=', 'pi2.id')
            ->join("{$pavTable} as pav", 'p.id', '=', 'pav.product_id')
            ->where('p.published', $this->publishedEnabled())
            ->whereNull('p.deleted_at')
            ->whereNull('pi2.deleted_at')
            ->whereIn('pi2.category_id', $categoryIds)
            ->select('pav.attribute_id as attr_id');

        return $fromGroups
            ->union($fromInterfaceValues)
            ->union($fromProductValues)
            ->distinct()
            ->pluck('attr_id')
            ->all();
    }

    /**
     * @return array<int, list<int|string>>
     */
    private function availableValueIds(array $categoryIds, array $attributeIds): array
    {
        $productClass = $this->productModel();
        $interfacesTable = ShopTables::name('product_interfaces');
        $piavTable = ShopTables::name('product_interface_attribute_values');
        $pavTable = ShopTables::name('product_attribute_values');

        $interfaceIdSub = DB::table($interfacesTable)
            ->whereIn('category_id', $categoryIds)
            ->whereNull('deleted_at')
            ->select('id');

        $productIdSub = $productClass::query()
            ->active()
            ->whereHas('productInterface', fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->toBase()
            ->select('id');

        $fromInterfaces = DB::table($piavTable)
            ->whereIn('product_interface_id', $interfaceIdSub)
            ->whereIn('attribute_id', $attributeIds)
            ->select('attribute_id', 'attribute_value_id');

        $fromProducts = DB::table($pavTable)
            ->whereIn('product_id', $productIdSub)
            ->whereIn('attribute_id', $attributeIds)
            ->select('attribute_id', 'attribute_value_id');

        return $fromInterfaces
            ->union($fromProducts)
            ->get()
            ->groupBy('attribute_id')
            ->map(fn ($rows) => $rows->pluck('attribute_value_id')->unique()->values()->all())
            ->all();
    }

    /**
     * @return array{min: int, max: int}
     */
    public function priceRange(?int $userGroupId): array
    {
        $productClass = $this->productModel();
        $productsTable = (new $productClass)->getTable();
        $pricesTable = ShopTables::name('product_prices');
        $now = now();

        $activeProductSub = $productClass::query()->active()->toBase()->select('id');

        $priceBase = DB::table($pricesTable)
            ->whereIn('product_id', $activeProductSub)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));

        $agg = [];

        if ($userGroupId !== null) {
            $g = (clone $priceBase)->where('segment_id', $userGroupId)
                ->selectRaw('MIN(price) as lo, MAX(price) as hi')->first();
            if ($g?->lo !== null) {
                array_push($agg, $g->lo, $g->hi);
            }
        }

        $d = (clone $priceBase)->whereNull('segment_id')
            ->selectRaw('MIN(price) as lo, MAX(price) as hi')->first();
        if ($d?->lo !== null) {
            array_push($agg, $d->lo, $d->hi);
        }

        $withPriceSub = (clone $priceBase)->select('product_id')->distinct();

        $b = DB::table($productsTable)
            ->where('published', $this->publishedEnabled())
            ->whereNull('deleted_at')
            ->whereIn('id', $activeProductSub)
            ->whereNotIn('id', $withPriceSub)
            ->selectRaw('MIN(base_price) as lo, MAX(base_price) as hi')
            ->first();
        if ($b?->lo !== null) {
            array_push($agg, $b->lo, $b->hi);
        }

        return $agg === []
            ? ['min' => 0, 'max' => 0]
            : ['min' => (int) min($agg), 'max' => (int) max($agg)];
    }

    public function weightOptions(?int $categoryId, array $brandIds, array $attributeValueIds): Collection
    {
        $productClass = $this->productModel();
        $query = $productClass::query()->active()->whereNotNull('weight');

        if ($categoryId) {
            $ids = $this->descendantIds($categoryId);
            $query->whereHas('productInterface', fn ($q) => $q->whereIn('category_id', $ids));
        }

        if ($brandIds) {
            $query->whereHas('productInterface', fn ($q) => $q->whereIn('brand_id', $brandIds));
        }

        if ($attributeValueIds) {
            $this->applyAttributeFilter($query, $attributeValueIds);
        }

        return $query->distinct()
            ->pluck('weight')
            ->map(fn ($w) => $w !== null ? number_format((float) $w, 2, '.', '') : null)
            ->filter()
            ->unique()
            ->sort(fn (string $a, string $b) => (float) $a <=> (float) $b)
            ->values();
    }
}
