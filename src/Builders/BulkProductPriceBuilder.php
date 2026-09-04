<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Karnoweb\Shop\Exceptions\InvalidPriceAmountException;
use Karnoweb\Shop\Exceptions\InvalidPriceWindowException;
use Karnoweb\Shop\Exceptions\ProductInterfaceNotFoundException;
use Karnoweb\Shop\Support\Money;

/**
 * Fluent writer that applies one price shape to many products.
 *
 * @example
 * Shop::prices()
 *     ->forProductInterface($pi->id)
 *     ->tier('retail')
 *     ->currency('IRR')
 *     ->amount(1_200_000)
 *     ->saveAll();
 */
class BulkProductPriceBuilder
{
    /** @var list<int|string> */
    private array $productIds = [];

    private int|string|null $productInterfaceId = null;

    private ?string $tier = null;

    private ?int $segmentId = null;

    private bool $segmentIdSpecified = false;

    private ?int $branchId = null;

    private bool $branchIdSpecified = false;

    private ?string $currency = null;

    private int|float|null $amount = null;

    private ?Carbon $startsAt = null;

    private ?Carbon $endsAt = null;

    /**
     * @param  list<int|string>  $productIds
     */
    public function forProductIds(array $productIds): self
    {
        $this->productIds = array_values($productIds);

        return $this;
    }

    public function forProductInterface(int|string $productInterfaceId): self
    {
        $this->productInterfaceId = $productInterfaceId;

        return $this;
    }

    public function tier(?string $tier): self
    {
        $this->tier = $tier;

        return $this;
    }

    public function segmentId(?int $segmentId): self
    {
        $this->segmentId = $segmentId;
        $this->segmentIdSpecified = true;

        return $this;
    }

    public function branchId(?int $branchId): self
    {
        $this->branchId = $branchId;
        $this->branchIdSpecified = true;

        return $this;
    }

    public function currency(?string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function amount(int|float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function startsAt(\DateTimeInterface|string|null $startsAt): self
    {
        $this->startsAt = $startsAt !== null ? Carbon::parse($startsAt) : null;

        return $this;
    }

    public function endsAt(\DateTimeInterface|string|null $endsAt): self
    {
        $this->endsAt = $endsAt !== null ? Carbon::parse($endsAt) : null;

        return $this;
    }

    /**
     * @return Collection<int, Model>
     */
    public function saveAll(): Collection
    {
        if ($this->amount !== null && $this->amount < 0) {
            throw new InvalidPriceAmountException($this->amount);
        }

        if ($this->startsAt !== null && $this->endsAt !== null && $this->startsAt->greaterThan($this->endsAt)) {
            throw new InvalidPriceWindowException(
                $this->startsAt->toIso8601String(),
                $this->endsAt->toIso8601String()
            );
        }

        $productIds = $this->resolveProductIds();
        $created = collect();

        foreach ($productIds as $productId) {
            $builder = (new ProductPriceBuilder)
                ->productId($productId)
                ->tier($this->tier)
                ->currency($this->currency ?? Money::defaultCurrency())
                ->amount($this->amount ?? 0)
                ->startsAt($this->startsAt)
                ->endsAt($this->endsAt);

            if ($this->segmentIdSpecified) {
                $builder->segmentId($this->segmentId);
            }

            if ($this->branchIdSpecified) {
                $builder->branchId($this->branchId);
            }

            $row = $builder->save();
            $created->push($row instanceof Collection ? $row->first() : $row);
        }

        return $created;
    }

    /**
     * @return list<int|string>
     */
    private function resolveProductIds(): array
    {
        if ($this->productIds !== []) {
            return $this->productIds;
        }

        if ($this->productInterfaceId === null) {
            return [];
        }

        /** @var class-string<Model> $interfaceClass */
        $interfaceClass = config('shop.models.product_interface');

        $interface = $interfaceClass::query()->find($this->productInterfaceId);

        if ($interface === null) {
            throw new ProductInterfaceNotFoundException($this->productInterfaceId);
        }

        return $interface->products()->pluck('id')->all();
    }
}
