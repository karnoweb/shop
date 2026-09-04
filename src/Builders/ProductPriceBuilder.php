<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Karnoweb\Shop\Exceptions\InvalidPriceAmountException;
use Karnoweb\Shop\Exceptions\InvalidPriceWindowException;
use Karnoweb\Shop\Exceptions\ProductNotFoundException;
use Karnoweb\Shop\Models\ProductPrice;
use Karnoweb\Shop\Support\Money;
use Karnoweb\Shop\Support\ShopContext;

/**
 * Fluent writer for time-windowed {@see ProductPrice} records.
 *
 * `amount()` accepts a single integer (uses {@see self::currency()}) or a
 * map of currency => amount to create multiple rows.
 *
 * startsAt/endsAt are optional; when both are unset the row is non-expiring.
 *
 * @example
 * Shop::price()
 *     ->productId($product->id)
 *     ->currency('IRR')
 *     ->tier('retail')
 *     ->amount(1_200_000)
 *     ->save();
 */
class ProductPriceBuilder
{
    private int|string|null $productId = null;

    private ?string $tier = null;

    private ?int $segmentId = null;

    private bool $segmentIdSpecified = false;

    private ?int $branchId = null;

    private bool $branchIdSpecified = false;

    private ?string $currency = null;

    /** @var int|float|array<string, int|float>|null */
    private int|float|array|null $amount = null;

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

    /**
     * Set the soft host segment key (persisted on the `segment_id` column).
     * Pass null for the default (segment-less) price row.
     */
    public function segmentId(?int $segmentId): self
    {
        $this->segmentId = $segmentId;
        $this->segmentIdSpecified = true;

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
     * Set the soft host branch key. Null = global price.
     * When omitted, defaults to {@see ShopContext::branchId()} if a resolver is bound.
     */
    public function branchId(?int $branchId): self
    {
        $this->branchId = $branchId;
        $this->branchIdSpecified = true;

        return $this;
    }

    /** Set the currency code. Defaults to config('shop.money.default_currency'). */
    public function currency(?string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Set the price amount (smallest currency unit), or a map of currency => amount.
     *
     * @param  int|float|array<string, int|float>  $amount
     */
    public function amount(int|float|array $amount): self
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
     * Validate invariants and persist one or more price rows.
     *
     * @return Model|Collection<int, Model>
     *
     * @throws ProductNotFoundException When `productId` was not set, or does not exist.
     * @throws InvalidPriceAmountException When any amount is negative.
     * @throws InvalidPriceWindowException When `startsAt` is after `endsAt`.
     */
    public function save(): Model|Collection
    {
        $amounts = $this->normalizeAmounts();

        if (count($amounts) === 1) {
            $currency = array_key_first($amounts);

            return $this->createRow($currency, $amounts[$currency]);
        }

        $created = collect();

        foreach ($amounts as $currency => $amount) {
            $created->push($this->createRow($currency, $amount));
        }

        return $created;
    }

    /**
     * @return array<string, int|float>
     */
    private function normalizeAmounts(): array
    {
        if (is_array($this->amount)) {
            return $this->amount;
        }

        $currency = $this->currency ?? Money::defaultCurrency();

        return [$currency => $this->amount ?? 0];
    }

    private function createRow(string $currency, int|float|null $amount): Model
    {
        if ($this->productId === null || ! $this->productExists($this->productId)) {
            throw new ProductNotFoundException($this->productId);
        }

        if ($amount !== null && $amount < 0) {
            throw new InvalidPriceAmountException($amount);
        }

        if ($this->startsAt !== null && $this->endsAt !== null && $this->startsAt->greaterThan($this->endsAt)) {
            throw new InvalidPriceWindowException(
                $this->startsAt->toIso8601String(),
                $this->endsAt->toIso8601String()
            );
        }

        /** @var class-string<Model> $priceClass */
        $priceClass = config('shop.models.product_price');

        $attributes = [
            'product_id' => $this->productId,
            'currency' => $currency,
            'branch_id' => $this->branchIdSpecified
                ? $this->branchId
                : app(ShopContext::class)->branchId(),
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];

        if ($this->tier !== null) {
            $attributes['tier'] = $this->tier;
        }

        if ($this->segmentIdSpecified) {
            $attributes['segment_id'] = $this->segmentId;
        }

        if ($amount !== null) {
            $attributes['price'] = $amount;
        }

        return $priceClass::query()->create($attributes);
    }

    private function productExists(int|string $productId): bool
    {
        /** @var class-string<Model> $productClass */
        $productClass = config('shop.models.product');

        return $productClass::query()->whereKey($productId)->exists();
    }
}
