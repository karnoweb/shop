<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Translation\Concerns\HasTranslation;

/**
 * Lean catalog brand. Host extends for media, SEO, views.
 *
 * @property string|null $title
 * @property string|null $description
 * @property string|null $body
 */
class Brand extends BaseModel
{
    use HasTranslation;
    use SoftDeletes;

    /** @var list<string> */
    protected array $translatable = [
        'title',
        'description',
        'body',
    ];

    protected $fillable = [
        'published',
        'ordering',
        'slug',
        'view_count',
        'languages',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'ordering' => 'integer',
            'view_count' => 'integer',
            'languages' => 'array',
            'extra_attributes' => 'array',
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function productInterfaces(): HasMany
    {
        return $this->hasMany(
            config('shop.models.product_interface', ProductInterface::class)
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('published', true);
    }
}
