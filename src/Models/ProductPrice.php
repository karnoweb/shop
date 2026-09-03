<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time-windowed product price, optionally scoped to a host user group.
 */
class ProductPrice extends BaseModel
{
    protected $fillable = [
        'product_id',
        'user_group_id',
        'tier',
        'price',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:0',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.product', Product::class));
    }

    /**
     * Soft host user-group relation.
     */
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.user_group'));
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
        });
    }

    public function scopeForGroup(Builder $query, ?int $userGroupId): Builder
    {
        return $query->where('user_group_id', $userGroupId);
    }

    public function scopeForTier(Builder $query, ?string $tier): Builder
    {
        return $query->where('tier', $tier);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->whereNull('user_group_id');
    }
}
