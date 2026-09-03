<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Karnoweb\Shop\Contracts\CampaignPriceAdjuster;
use Karnoweb\Shop\Contracts\StorefrontContext;

readonly class ProductService
{
    public function __construct(
        private ProductPriceResolver $priceResolver,
        private ?CampaignPriceAdjuster $campaignPriceAdjuster = null,
        private ?StorefrontContext $storefrontContext = null,
    ) {}

    /**
     * Batch-resolve card data for a collection of products.
     *
     * @return Collection<int|string, array<string, mixed>>
     */
    public function resolveForProducts(EloquentCollection $products): Collection
    {
        $user = auth()->user();
        $productIds = $products->pluck('id')->all();

        $wishlistIds = $this->storefrontContext?->wishlistProductIds($productIds) ?? [];
        $cartIds = $this->storefrontContext?->cartProductIds($productIds) ?? [];
        $compareIds = $this->storefrontContext?->compareProductIds($productIds) ?? [];
        $ratings = $this->storefrontContext?->ratingsForProducts($products) ?? [];

        return $products->mapWithKeys(function (Model $product) use ($user, $wishlistIds, $cartIds, $compareIds, $ratings) {
            $priceData = $this->resolvePrice($product, $user);

            return [
                $product->getKey() => [
                    'base_price' => $priceData['base_price'],
                    'final_price' => $priceData['final_price'],
                    'has_discount' => $priceData['has_discount'],
                    'discount_percent' => $priceData['discount_percent'],
                    'is_in_wishlist' => in_array($product->getKey(), $wishlistIds, true),
                    'is_in_cart' => in_array($product->getKey(), $cartIds, true),
                    'is_in_compare' => in_array($product->getKey(), $compareIds, true),
                    'rating' => $ratings[$product->getKey()] ?? 0.0,
                ],
            ];
        });
    }

    /**
     * @return array{base_price: int, final_price: int, has_discount: bool, discount_percent: mixed}
     */
    public function resolvePrice(Model $product, mixed $user): array
    {
        $basePrice = $this->priceResolver->resolve($product, $user);

        if ($this->campaignPriceAdjuster !== null) {
            $adjusted = $this->campaignPriceAdjuster->adjust($product, $user, $basePrice);

            if ($adjusted !== null) {
                return $adjusted;
            }
        }

        return [
            'base_price' => $basePrice,
            'final_price' => $basePrice,
            'has_discount' => false,
            'discount_percent' => null,
        ];
    }

    public function getRating(?Model $interface): float
    {
        return $this->storefrontContext?->ratingForInterface($interface) ?? 0.0;
    }

    public function isInWishlist(?Model $product): bool
    {
        return $this->storefrontContext?->isInWishlist($product) ?? false;
    }

    public function isInCompare(?Model $product): bool
    {
        return $this->storefrontContext?->isInCompare($product) ?? false;
    }

    public function isInCart(?Model $product): bool
    {
        return $this->storefrontContext?->isInCart($product) ?? false;
    }
}
