<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Shop\DTOs\PriceQuote;
use Karnoweb\Shop\Exceptions\ProductNotFoundException;
use Karnoweb\Shop\Services\QuoteService;

/**
 * Fluent reader that produces a {@see PriceQuote} DTO — pure catalog output,
 * safe to hand off to the host/commerce checkout flow via
 * {@see PriceQuote::toCommerceSnapshot()}.
 *
 * @example
 * $quote = Shop::quote()
 *     ->productId($product->id)
 *     ->tier('retail')
 *     ->userGroupId(7)
 *     ->resolve();
 */
class QuoteBuilder
{
    private int|string|null $productId = null;

    private string $itemType = 'shop.product';

    private ?string $tier = null;

    private ?int $segmentId = null;

    public function __construct(
        private readonly QuoteService $quoteService
    ) {}

    /** Set the target product id. */
    public function productId(int|string $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    /**
     * Generic alias for {@see self::productId()} — sets the id of the
     * sellable item being quoted. Only `itemType('shop.product')` (the
     * default) currently resolves to anything; other item types are reserved
     * for future use (see {@see PriceQuote}).
     */
    public function itemId(int|string $itemId): self
    {
        return $this->productId($itemId);
    }

    /**
     * Set the generic sellable-item type reported on the resulting
     * {@see PriceQuote}. Defaults to `"shop.product"` and
     * does not change how the item is resolved — this package only resolves
     * catalog products today.
     */
    public function itemType(string $itemType): self
    {
        $this->itemType = $itemType;

        return $this;
    }

    /** Set the portable price tier (e.g. 'retail', 'wholesale'). */
    public function tier(?string $tier): self
    {
        $this->tier = $tier;

        return $this;
    }

    /** Set the soft host segment key (e.g. a user-group id). */
    public function segmentId(?int $segmentId): self
    {
        $this->segmentId = $segmentId;

        return $this;
    }

    /**
     * @deprecated Alias for {@see self::segmentId()} — kept for backward compatibility.
     */
    public function userGroupId(?int $userGroupId): self
    {
        return $this->segmentId($userGroupId);
    }

    /**
     * Resolve the quote for the configured product/tier/user-group.
     *
     * @throws ProductNotFoundException When `productId` was not set, or does not exist.
     */
    public function resolve(): PriceQuote
    {
        /** @var class-string<Model> $productClass */
        $productClass = config('shop.models.product');

        $product = $this->productId !== null
            ? $productClass::query()->find($this->productId)
            : null;

        if ($product === null) {
            throw new ProductNotFoundException($this->productId);
        }

        return $this->quoteService->resolve($product, $this->segmentId, $this->tier, $this->itemType);
    }
}
