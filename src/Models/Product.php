<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Shop\Support\ShopTables;

/**
 * Lean sellable SKU / variant. Host extends for CMS traits, cache, media helpers.
 */
class Product extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'is_main',
        'published',
        'sku',
        'base_price',
        'stock',
        'minimum_sale',
        'maximum_sale',
        'weight',
        'height',
        'length',
        'width',
        'searchable_title',
        'extra_attributes',
        'default_uom_code',
        'product_interface_id',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'published' => 'boolean',
            'searchable_title' => 'json',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'minimum_sale' => 'integer',
            'maximum_sale' => 'integer',
            'stock' => 'integer',
            'base_price' => 'decimal:0',
            'extra_attributes' => 'array',
        ];
    }

    public function productInterface(): BelongsTo
    {
        return $this->belongsTo(
            config('shop.models.product_interface', ProductInterface::class),
            'product_interface_id'
        );
    }

    public function attributesList(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.attribute', Attribute::class),
            ShopTables::name('product_attribute_values'),
            'product_id',
            'attribute_id'
        )->withPivot('attribute_value_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.attribute_value', AttributeValue::class),
            ShopTables::name('product_attribute_values'),
            'product_id',
            'attribute_value_id'
        )->withPivot('attribute_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(config('shop.models.product_price', ProductPrice::class));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('products.published', true)
            ->whereHas('productInterface', fn ($q) => $q->where('published', true))
            ->whereNull('products.deleted_at');
    }

    public function scopeInStock(Builder $query): Builder
    {
        /** @deprecated Prefer inventory-backed availability via host {@see HasInventory} / {@see Inventory::stock()->available()}. */
        return $query->where('stock', '>', 0);
    }

    public function scopeMain(Builder $query): Builder
    {
        return $query->where('is_main', true);
    }

    public function activePrice(): HasOne
    {
        return $this->hasOne(config('shop.models.product_price', ProductPrice::class))
            ->ofMany(
                ['segment_id' => 'max', 'starts_at' => 'max'],
                function (Builder $query) {
                    $query->active();
                }
            );
    }

    public function defaultActivePrice(): HasOne
    {
        return $this->hasOne(config('shop.models.product_price', ProductPrice::class))
            ->ofMany(
                ['starts_at' => 'max'],
                function (Builder $query) {
                    $query->active()->whereNull('segment_id');
                }
            );
    }

    public function groupActivePrice(): HasOne
    {
        return $this->hasOne(config('shop.models.product_price', ProductPrice::class))
            ->ofMany(
                ['starts_at' => 'max'],
                function (Builder $query) {
                    $query->active()->whereNotNull('segment_id');
                }
            );
    }

    /**
     * Legacy catalog stock column. Prefer inventory on the host subclass.
     *
     * @deprecated Use {@see Karnoweb\Inventory\Facades\Inventory::stock()->available()} via host {@see HasInventory} instead.
     */
    public function getRemainingStockAttribute(): int
    {
        return (int) $this->stock;
    }

    /**
     * @deprecated Derives from legacy {@see $stock}. Host should override using inventory availability.
     */
    public function getMaxCartQuantityAttribute(): int
    {
        $stock = (int) ($this->stock ?? 0);
        $maxSale = (int) ($this->maximum_sale ?? 0);

        return $maxSale > 0 ? min($stock, $maxSale) : $stock;
    }
}
