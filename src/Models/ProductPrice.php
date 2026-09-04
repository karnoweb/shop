<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time-windowed product price, optionally scoped to a host segment
 * (typically a user group, but generic enough for any soft host
 * segmentation key — see `segment_id`).
 */
class ProductPrice extends BaseModel
{
    protected $fillable = [
        'product_id',
        'segment_id',
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
     * Soft host segment relation (typically the host's user-group model).
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.user_group'), 'segment_id');
    }

    /**
     * @deprecated Alias for {@see self::segment()} — kept for backward compatibility.
     */
    public function userGroup(): BelongsTo
    {
        return $this->segment();
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

    public function scopeForSegment(Builder $query, int|string|null $segmentId): Builder
    {
        return $query->where('segment_id', $segmentId);
    }

    /**
     * @deprecated Alias for {@see self::scopeForSegment()} — kept for backward compatibility.
     */
    public function scopeForGroup(Builder $query, int|string|null $userGroupId): Builder
    {
        return $this->scopeForSegment($query, $userGroupId);
    }

    public function scopeForTier(Builder $query, ?string $tier): Builder
    {
        return $query->where('tier', $tier);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->whereNull('segment_id');
    }
}
