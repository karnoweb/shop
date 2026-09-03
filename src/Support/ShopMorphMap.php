<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Support;

use Illuminate\Database\Eloquent\Relations\Relation;

final class ShopMorphMap
{
    public static function register(): void
    {
        $map = config('shop.morph_map', []);

        if ($map === []) {
            return;
        }

        Relation::morphMap($map, merge: true);
    }
}
