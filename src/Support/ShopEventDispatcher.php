<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dispatch shop catalog events after the surrounding DB transaction commits.
 */
final class ShopEventDispatcher
{
    public static function dispatch(object $event): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(static fn () => event($event));

            return;
        }

        event($event);
    }
}
