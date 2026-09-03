<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolve product price based on user group and active date windows.
 *
 * Logic order:
 * 1. Active price for (product + user_group) — if user has group
 * 2. Active price for (product + null group) — default price
 * 3. Fallback to product.base_price
 */
class ProductPriceResolver
{
    /**
     * @param Model       $product Product model instance
     * @param object|null $user    User with optional profile->user_group_id
     * @param string|null $tier    Optional portable price tier (e.g. retail, wholesale)
     */
    public function resolve(Model $product, ?object $user = null, ?string $tier = null): int
    {
        /** @var class-string<Model> $priceClass */
        $priceClass = config('shop.models.product_price');

        $userGroupId = data_get($user, 'profile.user_group_id');

        if ($userGroupId !== null) {
            $groupPrice = $priceClass::query()
                ->where('product_id', $product->getKey())
                ->where('user_group_id', $userGroupId)
                ->active()
                ->latest('starts_at')
                ->first();

            if ($groupPrice) {
                return (int) $groupPrice->price;
            }
        }

        if ($tier !== null) {
            $tierPrice = $priceClass::query()
                ->where('product_id', $product->getKey())
                ->forTier($tier)
                ->active()
                ->latest('starts_at')
                ->first();

            if ($tierPrice) {
                return (int) $tierPrice->price;
            }
        }

        $defaultPrice = $priceClass::query()
            ->where('product_id', $product->getKey())
            ->whereNull('user_group_id')
            ->active()
            ->latest('starts_at')
            ->first();

        if ($defaultPrice) {
            return (int) $defaultPrice->price;
        }

        return (int) $product->getAttribute('base_price');
    }
}
