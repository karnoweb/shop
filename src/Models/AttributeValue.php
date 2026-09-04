<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Karnoweb\Shop\Support\ShopTables;
use Karnoweb\Translation\Concerns\HasTranslation;

/**
 * Lean attribute value. Host extends for activity log.
 *
 * @property string|null $title
 */
class AttributeValue extends BaseModel
{
    use HasTranslation;

    /** @var list<string> */
    protected array $translatable = [
        'title',
    ];

    protected $fillable = [
        'order',
        'attribute_id',
        'languages',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'languages' => 'array',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.attribute', Attribute::class));
    }

    public function productInterfaces(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.product_interface', ProductInterface::class),
            ShopTables::name('product_interface_attribute_values')
        )->withPivot('attribute_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.product', Product::class),
            ShopTables::name('product_attribute_values')
        )->withPivot('attribute_id');
    }
}
