<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Shop\Events\ProductSaved;
use Karnoweb\Shop\Models\Product;
use Karnoweb\Shop\Support\ShopContext;
use Karnoweb\Shop\Support\ShopEventDispatcher;

/**
 * Fluent builder for creating catalog {@see Product} records.
 *
 * Each `Shop::product()` call returns an isolated builder instance. On
 * {@see self::create()}, dispatches {@see ProductSaved} via
 * {@see ShopEventDispatcher} (fires after DB commit when inside a transaction).
 *
 * @example
 * Shop::product()
 *     ->productInterfaceId($productInterface->id)
 *     ->sku('COF-1KG')
 *     ->basePrice(1_200_000)
 *     ->published(true)
 *     ->isMain(true)
 *     ->create();
 */
class ProductBuilder
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    private bool $branchIdSpecified = false;

    /** Set the owning product interface id. */
    public function productInterfaceId(int|string $productInterfaceId): self
    {
        $this->attributes['product_interface_id'] = $productInterfaceId;

        return $this;
    }

    /** Set the SKU. */
    public function sku(string $sku): self
    {
        $this->attributes['sku'] = $sku;

        return $this;
    }

    /** Set the base price (smallest currency unit, integer). */
    public function basePrice(int $basePrice): self
    {
        $this->attributes['base_price'] = $basePrice;

        return $this;
    }

    /** Set the published flag. */
    public function published(bool $published = true): self
    {
        $this->attributes['published'] = $published;

        return $this;
    }

    /** Flag this product as the main/default variant for its product interface. */
    public function isMain(bool $isMain = true): self
    {
        $this->attributes['is_main'] = $isMain;

        return $this;
    }

    /**
     * Set the legacy `stock` column.
     *
     * @deprecated Prefer inventory-backed stock via the host's `karnoweb/laravel-inventory` integration.
     */
    public function stock(int $stock): self
    {
        $this->attributes['stock'] = $stock;

        return $this;
    }

    /**
     * Set the optional default unit-of-measure code (e.g. 'kg', 'pcs') — purely
     * informational metadata for purchase/sales documents. This package never
     * integrates with `karnoweb/laravel-inventory` UOM tables here.
     */
    public function defaultUomCode(?string $uomCode): self
    {
        $this->attributes['default_uom_code'] = $uomCode;

        return $this;
    }

    /** Set weight in grams. Dimensions belong in extra_attributes. */
    public function weightGrams(?int $grams): self
    {
        $this->attributes['weight_grams'] = $grams;

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

    /** Set a single raw attribute understood by the configured Product model (escape hatch). */
    public function attribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Merge into the structured `extra_attributes` JSON column — the
     * documented, query-friendly extension point for business-specific data
     * that doesn't warrant a dedicated column (see docs/usage.md).
     *
     * Repeated calls merge (shallow) into the same array rather than
     * replacing it, so callers can build it up incrementally.
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
     * Persist the product using the model configured at `shop.models.product`,
     * then dispatch {@see ProductSaved} after commit.
     */
    public function create(): Model
    {
        /** @var class-string<Model> $class */
        $class = config('shop.models.product');

        $attributes = $this->attributes;

        if (! $this->branchIdSpecified) {
            $attributes['branch_id'] = app(ShopContext::class)->branchId();
        }

        /** @var Model $product */
        $product = $class::query()->create($attributes);

        ShopEventDispatcher::dispatch(new ProductSaved(
            productId: $product->getKey(),
            productInterfaceId: $product->getAttribute('product_interface_id'),
        ));

        return $product;
    }
}
