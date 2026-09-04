<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Support;

use Karnoweb\Shop\Contracts\BranchContextResolverContract;

/**
 * Optional runtime catalog context (currently branch).
 *
 * Resolved from {@see BranchContextResolverContract} when the host binds one;
 * otherwise every id is null and callers must pass scope explicitly.
 */
final class ShopContext
{
    public function __construct(
        private readonly ?BranchContextResolverContract $branchResolver = null,
    ) {}

    public function branchId(): ?int
    {
        return $this->branchResolver?->branchId();
    }
}
