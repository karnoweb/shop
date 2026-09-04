<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Karnoweb\Shop\Support\ShopTables;

abstract class BaseModel extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * Resolve the physical table name via {@see ShopTables}, so the
     * configured prefix (`shop.general.prefix`, default "shp_") and any
     * exact `shop.tables.<key>` override apply automatically.
     *
     * `$this->table`, when set by a subclass, is treated as the unprefixed
     * base key (e.g. "brands") — never double-prefixed if it already starts
     * with the current prefix.
     */
    public function getTable(): string
    {
        $key = $this->table ?? str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));

        return ShopTables::name($key);
    }
}
