<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared `forBranch` query semantics for catalog tables that carry a soft `branch_id`.
 *
 * `shop.catalog.branch_mode`:
 * - `strict`: only rows whose `branch_id` equals the given id
 * - `inherit_global`: rows whose `branch_id` is the given id **or** null
 *
 * A null `$branchId` always means "global catalog only" (`branch_id` is null).
 */
trait BranchScope
{
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        $column = $query->qualifyColumn('branch_id');

        if ($branchId === null) {
            return $query->whereNull($column);
        }

        $mode = (string) config('shop.catalog.branch_mode', 'inherit_global');

        if ($mode === 'strict') {
            return $query->where($column, $branchId);
        }

        return $query->where(function (Builder $inner) use ($column, $branchId): void {
            $inner->whereNull($column)->orWhere($column, $branchId);
        });
    }
}
