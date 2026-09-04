<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Karnoweb\Shop\Exceptions\InvalidPriceAmountException;
use Karnoweb\Shop\Exceptions\InvalidPriceWindowException;
use Karnoweb\Shop\Exceptions\ProductNotFoundException;
use Karnoweb\Shop\Models\ProductPrice;

/**
 * Fluent writer for time-windowed {@see ProductPrice} records.
 *
 * This is a writer, not a resolver — use `Shop::pricing()` or `Shop::quote()`
 * to read prices back. Each `Shop::price()` call returns an isolated builder
 * instance.
 *
 * @example
 * Shop::price()
 *     ->productId($product->id)
 *     ->tier('retail')
 *     ->userGroupId(null)
 *     ->amount(1_200_000)
 *     ->startsAt(now()->subDay())
 *     ->endsAt(now()->addMonth())
 *     ->save();
 */
class ProductPriceBuilder
{
    private int|string|null $productId = null;

    private ?string $tier = null;

    private ?int $userGroupId = null;

    private bool $userGroupIdSpecified = false;

    private int|float|null $amount = null;

    private ?Carbon $startsAt = null;

    private ?Carbon $endsAt = null;

    /** Set the target product id. */
    public function productId(int|string $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    /** Set the portable price tier (e.g. 'retail', 'wholesale'). */
    public function tier(?string $tier): self
    {
        $this->tier = $tier;

        return $this;
    }

    /** Set the soft host user-group key. Pass null for the default (group-less) price row. */
    public function userGroupId(?int $userGroupId): self
    {
        $this->userGroupId = $userGroupId;
        $this->userGroupIdSpecified = true;

        return $this;
    }

    /** Set the price amount (smallest currency unit). Must be >= 0. */
    public function amount(int|float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /** Set the window start (inclusive). Null means "always started". */
    public function startsAt(\DateTimeInterface|string|null $startsAt): self
    {
        $this->startsAt = $startsAt !== null ? Carbon::parse($startsAt) : null;

        return $this;
    }

    /** Set the window end (inclusive). Null means "never ends". */
    public function endsAt(\DateTimeInterface|string|null $endsAt): self
    {
        $this->endsAt = $endsAt !== null ? Carbon::parse($endsAt) : null;

        return $this;
    }

    /**
     * Validate invariants and persist the price row using the model
     * configured at `shop.models.product_price`.
     *
     * @throws ProductNotFoundException When `productId` was not set, or does not exist.
     * @throws InvalidPriceAmountException When `amount` is negative.
     * @throws InvalidPriceWindowException When `startsAt` is after `endsAt`.
     */
    public function save(): Model
    {
        if ($this->productId === null || ! $this->productExists($this->productId)) {
            throw new ProductNotFoundException($this->productId);
        }

        if ($this->amount !== null && $this->amount < 0) {
            throw new InvalidPriceAmountException($this->amount);
        }

        if ($this->startsAt !== null && $this->endsAt !== null && $this->startsAt->greaterThan($this->endsAt)) {
            throw new InvalidPriceWindowException(
                $this->startsAt->toIso8601String(),
                $this->endsAt->toIso8601String()
            );
        }

        /** @var class-string<Model> $priceClass */
        $priceClass = config('shop.models.product_price');

        $attributes = ['product_id' => $this->productId];

        if ($this->tier !== null) {
            $attributes['tier'] = $this->tier;
        }

        if ($this->userGroupIdSpecified) {
            $attributes['user_group_id'] = $this->userGroupId;
        }

        if ($this->amount !== null) {
            $attributes['price'] = $this->amount;
        }

        $attributes['starts_at'] = $this->startsAt;
        $attributes['ends_at'] = $this->endsAt;

        return $priceClass::query()->create($attributes);
    }

    private function productExists(int|string $productId): bool
    {
        /** @var class-string<Model> $productClass */
        $productClass = config('shop.models.product');

        return $productClass::query()->whereKey($productId)->exists();
    }
}
