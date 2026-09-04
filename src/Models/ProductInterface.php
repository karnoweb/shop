<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Shop\Enums\ProductInterfaceTypeEnum;
use Karnoweb\Shop\Enums\ProductKindEnum;
use Karnoweb\Shop\Enums\VariantsStatusEnum;
use Karnoweb\Shop\Support\BranchScope;
use Karnoweb\Shop\Support\ShopTables;
use Karnoweb\Translation\Concerns\HasTranslation;

/**
 * Lean product interface (parent catalog entry). Host extends for CMS traits.
 *
 * @property string|null $title
 * @property string|null $description
 * @property string|null $body
 */
class ProductInterface extends BaseModel
{
    use BranchScope;
    use HasTranslation;
    use SoftDeletes;

    /** @var list<string> */
    protected array $translatable = [
        'title',
        'description',
        'body',
    ];

    protected $fillable = [
        'category_id',
        'branch_id',
        'slug',
        'type',
        'kind',
        'variants_status',
        'variants_hash',
        'brand_id',
        'warning_quantity',
        'max_discount_percent',
        'need_stock_confirm',
        'ladder_at',
        'view_count',
        'comment_count',
        'like_count',
        'wish_count',
        'published',
        'published_at',
        'languages',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductInterfaceTypeEnum::class,
            'kind' => ProductKindEnum::class,
            'variants_status' => VariantsStatusEnum::class,
            'ladder_at' => 'datetime',
            'published_at' => 'datetime',
            'published' => 'boolean',
            'need_stock_confirm' => 'boolean',
            'view_count' => 'integer',
            'comment_count' => 'integer',
            'like_count' => 'integer',
            'wish_count' => 'integer',
            'max_discount_percent' => 'integer',
            'warning_quantity' => 'integer',
            'languages' => 'array',
            'extra_attributes' => 'array',
        ];
    }

    public function mainProduct(): HasOne
    {
        return $this->hasOne(config('shop.models.product', Product::class), 'product_interface_id')
            ->where('is_main', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(config('shop.models.product', Product::class));
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.brand', Brand::class));
    }

    /**
     * Soft host category relation.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.category'));
    }

    public function secondaryCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.category'),
            ShopTables::name('product_interface_secondary_categories'),
            'product_interface_id',
            'category_id'
        );
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.attribute', Attribute::class),
            ShopTables::name('product_interface_attributes'),
            'product_interface_id',
            'attribute_id'
        )->withPivot('codding');
    }

    public function coddingAttributes(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.attribute', Attribute::class),
            ShopTables::name('product_interface_attributes'),
            'product_interface_id',
            'attribute_id'
        )
            ->wherePivot('codding', true)
            ->withPivot('special', 'codding');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.attribute_value', AttributeValue::class),
            ShopTables::name('product_interface_attribute_values')
        )->withPivot('attribute_id');
    }

    public function complementaryProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            config('shop.models.product_interface', static::class),
            ShopTables::name('product_interface_complementary'),
            'product_interface_id',
            'complementary_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
