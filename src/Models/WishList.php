<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Lean wishlist entry. Host may extend for presentation helpers.
 */
class WishList extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'user_wishlists';

    protected $fillable = [
        'user_id',
        'morphable_id',
        'morphable_type',
    ];

    public function morphable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Soft host user relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.user'));
    }
}
