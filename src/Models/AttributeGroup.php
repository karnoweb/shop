<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Karnoweb\Shop\Support\ShopTables;
use Karnoweb\Translation\Concerns\HasTranslation;

/**
 * Lean attribute group. Host extends for category pivots.
 *
 * @property string|null $title
 * @property string|null $description
 */
class AttributeGroup extends BaseModel
{
    use HasTranslation;

    /** @var list<string> */
    protected array $translatable = [
        'title',
        'description',
    ];

    protected $fillable = [
        'languages',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
        ];
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.attribute', Attribute::class),
            ShopTables::name('attribute_attribute_group'),
            'attribute_group_id',
            'attribute_id'
        );
    }

    /**
     * Soft host category relation — category model resolved from config.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.category'),
            ShopTables::name('category_attribute_group')
        );
    }
}
