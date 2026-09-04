<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Shop\Events\ProductSaved;
use Karnoweb\Shop\Models\Product;
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

    /** Set a single raw attribute understood by the configured Product model (escape hatch). */
    public function attribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

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

        /** @var Model $product */
        $product = $class::query()->create($this->attributes);

        ShopEventDispatcher::dispatch(new ProductSaved(
            productId: $product->getKey(),
            productInterfaceId: $product->getAttribute('product_interface_id'),
        ));

        return $product;
    }
}
