<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Contracts;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Optional host bridge for storefront session state (wishlist, cart, compare, ratings).
 */
interface StorefrontContext
{
    /**
     * @param list<int|string> $productIds
     *
     * @return list<int>
     */
    public function wishlistProductIds(array $productIds): array;

    /**
     * @param list<int|string> $productIds
     *
     * @return list<int>
     */
    public function cartProductIds(array $productIds): array;

    /**
     * @param list<int|string> $productIds
     *
     * @return list<int>
     */
    public function compareProductIds(array $productIds): array;

    /**
     * @return array<int|string, float>
     */
    public function ratingsForProducts(EloquentCollection $products): array;

    public function ratingForInterface(?Model $interface): float;

    public function isInWishlist(?Model $product): bool;

    public function isInCart(?Model $product): bool;

    public function isInCompare(?Model $product): bool;
}
