<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Shop\Enums\CampaignConditionTypeEnum;
use Karnoweb\Shop\Enums\CampaignTypeEnum;

/**
 * Shop marketing campaign (not CRM Campaign).
 *
 * Lean model: validity scopes and type helpers live here.
 * Order/discount condition matching stays on the host subclass (commerce-coupled).
 *
 * @property string|null $title Virtual via host translation layer when extended
 */
class Campaign extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'discount_id',
        'campaign_type',
        'conditions',
        'condition_logic',
        'priority',
        'is_active',
        'apply_automatically',
        'exclude_manual_orders',
        'starts_at',
        'expires_at',
        'created_by',
        'languages',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'campaign_type' => CampaignTypeEnum::class,
            'is_active' => 'boolean',
            'apply_automatically' => 'boolean',
            'exclude_manual_orders' => 'boolean',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'languages' => 'array',
        ];
    }

    /**
     * Soft commerce discount relation (config, no hard package dep).
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.discount'));
    }

    /**
     * Soft host user relation for creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('shop.models.user'), 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeAutoApply(Builder $query): Builder
    {
        return $query->where('apply_automatically', true);
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        return ! ($this->expires_at && $this->expires_at->isPast());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function isProductBased(): bool
    {
        return $this->campaign_type === CampaignTypeEnum::PRODUCT_BASED;
    }

    public function isOrderBased(): bool
    {
        return $this->campaign_type === CampaignTypeEnum::ORDER_BASED;
    }

    /**
     * @return list<string>
     */
    public function getAllowedConditionTypes(): array
    {
        if ($this->isProductBased()) {
            return [
                CampaignConditionTypeEnum::CATEGORY->value,
                CampaignConditionTypeEnum::BRAND->value,
                CampaignConditionTypeEnum::PRODUCT->value,
            ];
        }

        return [
            CampaignConditionTypeEnum::USER->value,
            CampaignConditionTypeEnum::USER_GROUP->value,
            CampaignConditionTypeEnum::MIN_ORDER_AMOUNT->value,
            CampaignConditionTypeEnum::MIN_ORDER_COUNT->value,
            CampaignConditionTypeEnum::FIRST_ORDER->value,
            CampaignConditionTypeEnum::DATE_RANGE->value,
        ];
    }
}
