<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Contracts;

/**
 * Optional host bridge that supplies the current catalog branch id.
 *
 * When unbound, callers must pass `branchId` explicitly. When bound,
 * builders and services may default to {@see self::branchId()}.
 */
interface BranchContextResolverContract
{
    public function branchId(): ?int;
}
