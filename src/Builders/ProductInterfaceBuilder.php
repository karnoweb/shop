<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Karnoweb\Shop\Contracts\SkuGeneratorContract;
use Karnoweb\Shop\Enums\ProductInterfaceTypeEnum;
use Karnoweb\Shop\Enums\ProductKindEnum;
use Karnoweb\Shop\Enums\VariantsStatusEnum;
use Karnoweb\Shop\Events\ProductSaved;
use Karnoweb\Shop\Models\Product;
use Karnoweb\Shop\Models\ProductInterface;
use Karnoweb\Shop\Support\ShopContext;
use Karnoweb\Shop\Support\ShopEventDispatcher;

/**
 * Fluent builder for creating catalog {@see ProductInterface} records.
 *
 * {@see self::create()} always persists the interface **and** exactly one
 * `is_main` {@see Product} in a single transaction.
 *
 * @example
 * Shop::productInterface()
 *     ->slug('coffee-beans-1kg')
 *     ->type('simple')
 *     ->kind('simple')
 *     ->brandId($brand->id)
 *     ->categoryId(10)
 *     ->published(true)
 *     ->create();
 */
class ProductInterfaceBuilder
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, mixed> */
    private array $mainProductAttributes = [];

    private bool $branchIdSpecified = false;

    /** Set the product interface slug. */
    public function slug(string $slug): self
    {
        $this->attributes['slug'] = $slug;

        return $this;
    }

    /** Set the variant structure: 'simple' or 'codding'. */
    public function type(string|ProductInterfaceTypeEnum $type): self
    {
        $this->attributes['type'] = $type instanceof ProductInterfaceTypeEnum ? $type->value : $type;

        return $this;
    }

    /**
     * Set inventory/sell behavior:
     * 'simple'|'ingredient'|'composed'|'virtual'|'bundle' (see {@see ProductKindEnum}).
     */
    public function kind(string|ProductKindEnum $kind): self
    {
        $this->attributes['kind'] = $kind instanceof ProductKindEnum ? $kind->value : $kind;

        return $this;
    }

    /** Set the owning brand id (nullable soft relation). */
    public function brandId(int|string|null $brandId): self
    {
        $this->attributes['brand_id'] = $brandId;

        return $this;
    }

    /** Set the soft host category key — never FK-constrained by this package. */
    public function categoryId(int|string|null $categoryId): self
    {
        $this->attributes['category_id'] = $categoryId;

        return $this;
    }

    /**
     * Set the soft host branch key. Null = global catalog.
     * When omitted, defaults to {@see ShopContext::branchId()} if a resolver is bound.
     */
    public function branchId(?int $branchId): self
    {
        $this->attributes['branch_id'] = $branchId;
        $this->branchIdSpecified = true;

        return $this;
    }

    /** Set the published flag on the product interface. */
    public function published(bool $published = true): self
    {
        $this->attributes['published'] = $published;

        return $this;
    }

    /** Set the published_at timestamp (used by the `published()` scope on the model). */
    public function publishedAt(\DateTimeInterface|string|null $publishedAt): self
    {
        $this->attributes['published_at'] = $publishedAt;

        return $this;
    }

    /** Optional SKU for the auto-created main product. Generated when omitted. */
    public function sku(string $sku): self
    {
        $this->mainProductAttributes['sku'] = $sku;

        return $this;
    }

    /** Optional default UOM code inherited by the main product. */
    public function defaultUomCode(?string $uomCode): self
    {
        $this->mainProductAttributes['default_uom_code'] = $uomCode;

        return $this;
    }

    /** Optional weight in grams for the main product. */
    public function weightGrams(?int $grams): self
    {
        $this->mainProductAttributes['weight_grams'] = $grams;

        return $this;
    }

    /** Optional base price for the main product. */
    public function basePrice(int $basePrice): self
    {
        $this->mainProductAttributes['base_price'] = $basePrice;

        return $this;
    }

    /**
     * Published flag for the auto-created main product.
     * Defaults to false unless this is called.
     */
    public function productPublished(bool $published = true): self
    {
        $this->mainProductAttributes['published'] = $published;

        return $this;
    }

    /** Set a single raw attribute understood by the configured ProductInterface model (escape hatch). */
    public function attribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Merge into the structured `extra_attributes` JSON column.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function extra(array $attributes): self
    {
        $this->attributes['extra_attributes'] = array_merge(
            $this->attributes['extra_attributes'] ?? [],
            $attributes
        );

        return $this;
    }

    /**
     * Merge an arbitrary attribute array (escape hatch for host-specific columns).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function fill(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Persist the product interface and its main product in one transaction.
     * Dispatches {@see ProductSaved} after commit and returns the interface
     * with `mainProduct` loaded.
     */
    public function create(): Model
    {
        return DB::transaction(function (): Model {
            $branchId = $this->resolveBranchId();

            $attributes = $this->attributes;
            $attributes['branch_id'] = $branchId;
            $attributes['type'] ??= ProductInterfaceTypeEnum::SIMPLE->value;
            $attributes['kind'] ??= ProductKindEnum::SIMPLE->value;
            $attributes['variants_status'] ??= VariantsStatusEnum::READY->value;

            /** @var class-string<Model> $interfaceClass */
            $interfaceClass = config('shop.models.product_interface');

            /** @var Model $interface */
            $interface = $interfaceClass::query()->create($attributes);

            /** @var class-string<Model> $productClass */
            $productClass = config('shop.models.product');

            $sku = $this->mainProductAttributes['sku']
                ?? app(SkuGeneratorContract::class)->generate((string) $interface->getAttribute('slug'), []);

            $product = $productClass::query()->create([
                'product_interface_id' => $interface->getKey(),
                'is_main' => true,
                'sku' => $sku,
                'branch_id' => $branchId,
                'default_uom_code' => $this->mainProductAttributes['default_uom_code'] ?? null,
                'weight_grams' => $this->mainProductAttributes['weight_grams'] ?? null,
                'base_price' => $this->mainProductAttributes['base_price'] ?? 0,
                'published' => $this->mainProductAttributes['published'] ?? false,
            ]);

            ShopEventDispatcher::dispatch(new ProductSaved(
                productId: $product->getKey(),
                productInterfaceId: $interface->getKey(),
            ));

            $interface->load('mainProduct');

            return $interface;
        });
    }

    private function resolveBranchId(): ?int
    {
        if ($this->branchIdSpecified) {
            return isset($this->attributes['branch_id']) ? (int) $this->attributes['branch_id'] : null;
        }

        return app(ShopContext::class)->branchId();
    }
}
