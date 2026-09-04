<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Shop\Enums\ProductInterfaceTypeEnum;
use Karnoweb\Shop\Enums\ProductKindEnum;
use Karnoweb\Shop\Models\ProductInterface;

/**
 * Fluent builder for creating catalog {@see ProductInterface} records.
 *
 * Each `Shop::productInterface()` call returns an isolated builder instance.
 * `category_id` is a soft host key (see SHOP_PACKAGE.md §0) — it is stored as
 * a plain integer, never FK-constrained by this package.
 *
 * @example
 * Shop::productInterface()
 *     ->slug('coffee-beans-1kg')
 *     ->type('simple')
 *     ->kind('physical')
 *     ->brandId($brand->id)
 *     ->categoryId(10)
 *     ->published(true)
 *     ->create();
 */
class ProductInterfaceBuilder
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** Set the product interface slug. */
    public function slug(string $slug): self
    {
        $this->attributes['slug'] = $slug;

        return $this;
    }

    /** Set the catalog type (e.g. 'simple', 'codding', 'digital', 'service') — variant/configuration shape. */
    public function type(string|ProductInterfaceTypeEnum $type): self
    {
        $this->attributes['type'] = $type instanceof ProductInterfaceTypeEnum ? $type->value : $type;

        return $this;
    }

    /**
     * Set the generic, inventory-agnostic business classification:
     * 'physical'|'service'|'digital'|'bundle' (see {@see ProductKindEnum}).
     *
     * Independent from {@see self::type()} — `kind` is "what kind of thing is
     * this to the business", not the variant/configuration shape.
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
    public function categoryId(int|string $categoryId): self
    {
        $this->attributes['category_id'] = $categoryId;

        return $this;
    }

    /** Set the published flag. */
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

    /** Set a single raw attribute understood by the configured ProductInterface model (escape hatch). */
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

    /** Persist the product interface using the model configured at `shop.models.product_interface`. */
    public function create(): Model
    {
        /** @var class-string<Model> $class */
        $class = config('shop.models.product_interface');

        return $class::query()->create($this->attributes);
    }
}
