<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Shop\Enums\AttributeTypeEnum;
use Karnoweb\Shop\Support\ShopTables;
use Karnoweb\Translation\Concerns\HasTranslation;

/**
 * Lean catalog attribute. Host extends for activity log.
 *
 * @property string|null $title
 * @property mixed $pivot
 */
class Attribute extends BaseModel
{
    use HasTranslation;

    /** @var list<string> */
    protected array $translatable = [
        'title',
    ];

    protected $fillable = [
        'type',
        'filterable',
        'comparable',
        'special',
        'order',
        'languages',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeTypeEnum::class,
            'filterable' => 'boolean',
            'comparable' => 'boolean',
            'special' => 'boolean',
            'order' => 'integer',
            'languages' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(config('shop.models.attribute_value', AttributeValue::class), 'attribute_id');
    }

    public function productInterfaces(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.product_interface', ProductInterface::class),
            ShopTables::name('product_interface_attributes')
        )
            ->withPivot('special', 'codding')
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.attribute_group', AttributeGroup::class),
            ShopTables::name('attribute_attribute_group'),
            'attribute_id',
            'attribute_group_id'
        );
    }
}
