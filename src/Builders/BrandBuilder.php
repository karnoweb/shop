<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Shop\Models\Brand;

/**
 * Fluent builder for creating catalog {@see Brand} records.
 *
 * Each `Shop::brand()` call returns an isolated builder instance — state never
 * leaks across calls. The model class is resolved from `config('shop.models.brand')`
 * so a host subclass is used automatically when configured.
 *
 * @example
 * Shop::brand()->slug('acme')->published(true)->create();
 */
class BrandBuilder
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** Set the brand slug. */
    public function slug(string $slug): self
    {
        $this->attributes['slug'] = $slug;

        return $this;
    }

    /** Set the published flag. */
    public function published(bool $published = true): self
    {
        $this->attributes['published'] = $published;

        return $this;
    }

    /** Set display ordering. */
    public function ordering(int $ordering): self
    {
        $this->attributes['ordering'] = $ordering;

        return $this;
    }

    /** Set a single raw attribute understood by the configured Brand model (escape hatch). */
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

    /** Persist the brand using the model configured at `shop.models.brand`. */
    public function create(): Model
    {
        /** @var class-string<Model> $class */
        $class = config('shop.models.brand');

        return $class::query()->create($this->attributes);
    }
}
