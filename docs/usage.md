# Usage Guide — Catalog, Pricing & Quotes (English)

Canonical "0 → 100" guide to the `Shop` facade: the fluent catalog builders,
generic product kinds, extra attributes, price writing, and the quote
handoff to checkout/commerce. Contract-first — inputs, outputs, events, and
extension points. Implementation details live in `src/`.

For the pre-existing, Persian, section-by-section walkthrough of the
service-based surface (`Shop::products()`, `Shop::filters()`, host
integration), see [README.md](README.md) and its linked pages. This document
is the single English reference for the builder/quote surface and is kept in
sync with the Facade's `@method` annotations.

---

## Contents

- [Entry point](#entry-point)
- [Installation](#installation)
- [Table prefix & configurable table names](#table-prefix--configurable-table-names)
- [0 → 100: building a catalog](#0--100-building-a-catalog)
- [Product kinds (generic business classification)](#product-kinds-generic-business-classification)
- [Variant modeling: ProductInterface vs Product](#variant-modeling-productinterface-vs-product)
- [Extra attributes (structured JSON extension point)](#extra-attributes-structured-json-extension-point)
- [Price records (ProductPrice)](#price-records-productprice)
- [Quote: pure DTO handoff to commerce](#quote-pure-dto-handoff-to-commerce)
- [Existing pricing API (unchanged)](#existing-pricing-api-unchanged)
- [Existing product/filter services (unchanged)](#existing-productfilter-services-unchanged)
- [Validation & exceptions](#validation--exceptions)
- [Events](#events)
- [Extension points (host bridges)](#extension-points-host-bridges)
- [What stays in host](#what-stays-in-host)

---

## Entry point

Everything goes through the `Shop` facade or the injected `Karnoweb\Shop\Shop`
manager:

```php
use Karnoweb\Shop\Facades\Shop;
```

| Surface | Accessor | Returns |
|---|---|---|
| Config | `Shop::config($key, $default)` | `mixed` |
| Model class | `Shop::model($key)` / `Shop::newModel($key)` | `class-string<Model>` / `Model` |
| Brand builder | `Shop::brand()` | `Builders\BrandBuilder` |
| ProductInterface builder | `Shop::productInterface()` | `Builders\ProductInterfaceBuilder` |
| Product builder | `Shop::product()` | `Builders\ProductBuilder` |
| Price writer | `Shop::price()` | `Builders\ProductPriceBuilder` |
| Quote reader | `Shop::quote()` | `Builders\QuoteBuilder` |
| Quote service | `Shop::quotes()` | `Services\QuoteService` |
| Pricing (existing) | `Shop::pricing()` | `Services\ProductPriceResolver` |
| Product cards (existing) | `Shop::products()` | `Services\ProductService` |
| Filters (existing) | `Shop::filters()` | `Services\ProductFilterService` |

Every builder call (`brand()`, `productInterface()`, `product()`, `price()`,
`quote()`) returns a **fresh, isolated instance** — nothing is shared or
reset between calls, so builders are safe to reuse in loops without leaking
state.

## Installation

See the [package README](../README.md) for the full install steps. Short version:

```bash
composer require karnoweb/shop:^13.0
php artisan vendor:publish --tag=shop-config
php artisan vendor:publish --tag=shop-migrations
php artisan migrate
```

The whole catalog schema ships as a **single squashed migration**
(`database/migrations_squashed/2026_09_04_000000_create_shop_schema.php`) —
one file creates every table in its final form. The old incremental
migrations are kept under `database/migrations_legacy/` purely as historical
reference; they are never loaded or published.

Point `shop.models.*` (or the matching `SHOP_*_MODEL` env vars) at your host
subclasses (e.g. `App\Models\Brand extends Karnoweb\Shop\Models\Brand`).
Builders always resolve the model class through this config, so host
subclasses are used automatically — nothing in `src/Builders` hardcodes a
concrete model.

## Table prefix & configurable table names

Same pattern as `karnoweb/laravel-inventory`: every table this package owns
is resolved through `Karnoweb\Shop\Support\ShopTables::name($key)`, which
prepends `config('shop.general.prefix')` (env `SHOP_TABLE_PREFIX`, **default
`"shp_"`**) unless an exact override is configured at
`config('shop.tables.<key>')` (env `SHOP_TABLE_<KEY>`). This applies
identically to Eloquent models (via `Karnoweb\Shop\Models\BaseModel`) and to
the squashed schema migration, so prefix/overrides stay consistent.

**Configure the prefix (or any per-table override) BEFORE running
`php artisan migrate`** — changing it afterwards does not rename
already-created tables.

```env
# .env
SHOP_TABLE_PREFIX=shp_
# Optional exact override for a single table, bypassing the prefix:
SHOP_TABLE_BRANDS=catalog_brands
```

```php
use Karnoweb\Shop\Support\ShopTables;

ShopTables::name('products'); // "shp_products" by default
ShopTables::prefix();         // "shp_"
```

Table keys available under `shop.tables.*`: `brands`, `attribute_groups`,
`attributes`, `attribute_values`, `product_interfaces`, `products`,
`campaigns`, `product_prices`, `category_attribute_group`,
`attribute_attribute_group`, `product_interface_attributes`,
`product_interface_attribute_values`, `product_attribute_values`,
`product_interface_secondary_categories`, `product_interface_complementary`.

To keep pre-13.4 unprefixed table names (e.g. an existing deployment), set
`SHOP_TABLE_PREFIX=` (empty) before migrating.

## 0 → 100: building a catalog

The full scenario below uses **only public API** and is exercised end to end
by `tests/Feature/BuilderApiTest.php`.

```php
use Karnoweb\Shop\Facades\Shop;

$brand = Shop::brand()
    ->slug('acme')
    ->published(true)
    ->create();

$pi = Shop::productInterface()
    ->slug('coffee-beans-1kg')
    ->type('simple')
    ->kind('physical')        // physical|service|digital|bundle
    ->brandId($brand->id)
    ->categoryId(10)          // soft host key
    ->published(true)
    ->create();

$product = Shop::product()
    ->productInterfaceId($pi->id)
    ->sku('COF-1KG')
    ->isMain(true)
    ->basePrice(1_200_000)
    ->defaultUomCode('kg')    // optional, purely informational (see below)
    ->published(true)
    ->create();

Shop::price()
    ->productId($product->id)
    ->tier('retail')
    ->segmentId(null)
    ->amount(1_200_000)
    ->startsAt(now()->subDay())
    ->endsAt(now()->addMonth())
    ->save();

Shop::price()
    ->productId($product->id)
    ->tier('retail')
    ->segmentId(7)
    ->amount(1_000_000)
    ->startsAt(now()->subDay())
    ->endsAt(now()->addMonth())
    ->save();

$quote = Shop::quote()
    ->productId($product->id)
    ->tier('retail')
    ->segmentId(7)
    ->resolve(); // returns PriceQuote DTO

$snapshot = $quote->toCommerceSnapshot();
// $snapshot is a pure array, stable keys, no external class references.
```

**Inputs/outputs:**

| Builder | Required inputs | Returns |
|---|---|---|
| `Shop::brand()` | `slug()` (unique) | Model configured at `shop.models.brand` |
| `Shop::productInterface()` | `slug()` (unique), `categoryId()` | Model configured at `shop.models.product_interface` |
| `Shop::product()` | `productInterfaceId()` | Model configured at `shop.models.product` |

All three also accept `attribute($key, $value)` (single raw attribute),
`extra(array $attributes)` (structured JSON extension point — see below),
and `fill(array $attributes)` (bulk) as escape hatches for host-specific
columns without waiting on a new builder method.

`categoryId()` and `brandId()` are **soft host keys**: plain integers/ids,
stored without `->constrained()`/`->foreign()`. The host owns the `categories`
table (or equivalent); this package never creates or reads it directly.

## Product kinds (generic business classification)

`ProductInterface.kind` is a **generic, inventory-agnostic** business
classification — "what kind of thing is this to the business" — independent
from `type` (the variant/configuration shape: `simple`/`codding`/`digital`/`service`)
and independent from stock/inventory (`karnoweb/laravel-inventory` on the host):

| Kind | Meaning |
|---|---|
| `physical` (default) | A stockable, shippable good |
| `service` | A non-stock service (labor, consulting, subscription seat, ...) |
| `digital` | A non-stock digital good (license key, download, ...) |
| `bundle` | A composite/kit of other products (composition is host/commerce concern) |

```php
use Karnoweb\Shop\Enums\ProductKindEnum;

Shop::productInterface()->slug('consulting-hour')->kind('service')->categoryId(20)->create();
Shop::productInterface()->slug('ebook-license')->kind(ProductKindEnum::DIGITAL)->categoryId(21)->create();
```

`kind` accepts a plain string or a `ProductKindEnum` case and is cast to
`ProductKindEnum` on the model (`$productInterface->kind->value`,
`$productInterface->kind->title()` for a translated label). This package
never uses `kind` to gate stock/availability logic — that stays entirely
with the host's inventory integration.

## Variant modeling: ProductInterface vs Product

- **`ProductInterface`** — the parent: shared, translatable attributes
  (`title`, `description`, `body`), `type`, `kind`, `brand_id`, `category_id`,
  publishing flags. One row per "product family".
- **`Product`** — a variant/SKU: `sku`, `base_price`, `is_main`, and
  variant-specific attribute values. One or more rows per `ProductInterface`
  (`ProductInterface::hasMany(Product::class)` / `Product::belongsTo(ProductInterface::class)`).

```php
$pi = Shop::productInterface()->slug('t-shirt')->type('codding')->kind('physical')->categoryId(5)->create();

$small = Shop::product()->productInterfaceId($pi->id)->sku('TSHIRT-S')->basePrice(400_000)->isMain(true)->create();
$large = Shop::product()->productInterfaceId($pi->id)->sku('TSHIRT-L')->basePrice(420_000)->create();
```

Pricing, price windows, and quotes are always resolved **per `Product`**
(per SKU/variant), never per `ProductInterface` — see
[Price records](#price-records-productprice) below.

## Extra attributes (structured JSON extension point)

Both `ProductInterface.extra_attributes` and `Product.extra_attributes` are
nullable `json` columns (`Brand.extra_attributes` also exists, for parity),
cast to `array` on the model — a documented, query-friendly place for
business-specific data that doesn't warrant a dedicated column:

```php
$pi = Shop::productInterface()
    ->slug('coffee-beans-1kg')
    ->categoryId(10)
    ->extra(['origin' => 'brazil', 'roast' => 'medium'])
    ->create();

$product = Shop::product()
    ->productInterfaceId($pi->id)
    ->sku('COF-1KG')
    ->basePrice(1_200_000)
    ->extra(['weight_grams' => 1000])
    ->create();

$pi->extra_attributes;      // ['origin' => 'brazil', 'roast' => 'medium']
$product->extra_attributes; // ['weight_grams' => 1000]
```

`->extra(array $attributes)` is available on `Shop::brand()`,
`Shop::productInterface()`, and `Shop::product()`. Repeated calls **merge**
(shallow) into the same array rather than replacing it:

```php
Shop::productInterface()->extra(['a' => 1])->extra(['b' => 2]); // extra_attributes === ['a' => 1, 'b' => 2]
```

Because the column is plain `json`, it stays query-friendly with
`whereJsonContains()` / `->where('extra_attributes->origin', 'brazil')` on
supporting databases — no separate EAV tables are introduced.

## Default unit of measure (optional)

`Product.default_uom_code` is an optional, nullable string column — purely
informational metadata (e.g. `"kg"`, `"pcs"`) so host purchase/sales
documents can pick a sensible default unit without this package integrating
with `karnoweb/laravel-inventory`'s UOM tables:

```php
Shop::product()->productInterfaceId($pi->id)->sku('COF-1KG')->basePrice(1_200_000)->defaultUomCode('kg')->create();
```

If you'd rather not use the dedicated column, the same convention works via
`extra_attributes['default_uom_code']` — both are supported; the column
exists because it's a common-enough need to warrant one.

## Price records (ProductPrice)

`Shop::price()` is a **writer**, not a resolver — use it to create
time-windowed price rows, one per `Product` (SKU). Reading happens through
`Shop::pricing()` or `Shop::quote()` (below).

```php
Shop::price()
    ->productId($product->id)
    ->tier('retail')
    ->segmentId(null)
    ->amount(1_200_000)
    ->startsAt(now()->subDay())
    ->endsAt(now()->addMonth())
    ->save();

Shop::price()
    ->productId($product->id)
    ->tier('retail')
    ->segmentId(7)
    ->amount(1_000_000)
    ->startsAt(now()->subDay())
    ->endsAt(now()->addMonth())
    ->save();
```

**Inputs:** `productId()` (required), `tier()`, `segmentId()` (null =
default/segment-less row — a **soft host key**, persisted on the
`segment_id` column, never FK-constrained; `userGroupId()` is kept as an
alias), `amount()` (int|float, >= 0), `startsAt()` / `endsAt()`
(`DateTimeInterface|string|null`).

**Output:** the created model configured at `shop.models.product_price`.

**Invariants** (see [Validation & exceptions](#validation--exceptions)):
`startsAt <= endsAt` when both are set; `amount >= 0`; `productId` must
resolve to an existing row on the model configured at `shop.models.product`.

## Quote: a generic, pure DTO handoff to commerce

`Shop::quote()` resolves a **portable, immutable `PriceQuote` DTO** — no
dependency on `karnoweb/commerce` or any host class, in either direction.
It accepts `segmentId`/`tier` **explicitly**; it never assumes a host user
object shape (no `auth()->user()`, no `data_get($user, 'profile...')`).

```php
$quote = Shop::quote()
    ->productId($product->id)
    ->tier('retail')
    ->segmentId(7)
    ->resolve(); // returns Karnoweb\Shop\DTOs\PriceQuote
```

**Inputs:** `productId()` (required, must exist), `tier()` (optional),
`segmentId()` (optional — `userGroupId()` is kept as an alias). `itemId()` is
a generic alias for `productId()`, and `itemType()` (default
`"shop.product"`) labels *what kind of sellable* is being quoted — see below.

**Output — `PriceQuote` fields:**

| Field | Type | Meaning |
|---|---|---|
| `itemType` | `string` | Generic sellable-item type; currently always `"shop.product"` (see note below) |
| `itemId` | `int\|null` | Same as `productId` for the current `"shop.product"` item type |
| `productId` | `int` | Product (SKU) the quote is for — kept alongside `itemId` for backward compatibility |
| `tier` | `string\|null` | The tier the caller asked for |
| `segmentId` | `int\|null` | The soft host segmentation key the caller asked for (typically a user-group id) |
| `basePrice` | `int` | Price resolved by `ProductPriceResolver` for this tier/segment — see `source` — before any campaign adjustment |
| `finalPrice` | `int` | Price after optional campaign adjustment (equals `basePrice` when no adjuster is bound, or none applied) |
| `hasDiscount` | `bool` | Whether a campaign adjustment changed the price |
| `discountAmount` | `int` | `basePrice - finalPrice`, always `>= 0` |
| `discountPercent` | `float\|null` | Discount percent, when known/computable |
| `campaignId` | `int\|null` | Campaign id, when the host's `CampaignPriceAdjuster` reports one |
| `source` | `string` | Which strategy produced `basePrice`: `"user_group_price"` \| `"tier_price"` \| `"default_price"` \| `"base_price"` |
| `uomCode` | `string\|null` | The product's `default_uom_code`, when set |

```php
$quote->basePrice;   // int
$quote->source;      // "user_group_price" | "tier_price" | "default_price" | "base_price"
$quote->toCommerceSnapshot(); // array safe to store in commerce OrderItem.extra_attributes
```

**Why `itemType`/`itemId`?** `PriceQuote` is deliberately not "product only":
`QuoteService` only resolves catalog `Product` rows today (so `itemType` is
always `"shop.product"`, `itemId === productId`), but the DTO/snapshot shape
already leaves room for future sellable types (e.g. `"shop.variant"`,
`"shop.module"`) **without a contract change** on the commerce/host side.

### Handoff to commerce

`PriceQuote::toCommerceSnapshot()` returns a **plain array** of
scalars/`null` (no objects, no package classes) — the stable contract the
host/commerce layer can depend on:

```php
[
    'item_type' => 'shop.product',
    'item_id' => 123,
    'product_id' => 123,
    'tier' => 'retail',
    'segment_id' => 7,
    'user_group_id' => 7, // backward-compatible alias, same value as segment_id
    'base_price' => 1_000_000,
    'final_price' => 1_000_000,
    'has_discount' => false,
    'discount_amount' => 0,
    'discount_percent' => null,
    'campaign_id' => null,
    'source' => 'user_group_price',
    'uom_code' => null,
]
```

`item_type`/`item_id` are the minimum stable keys a generic "sellable
snapshot" consumer should read; `product_id` is kept alongside them for
existing snapshot consumers. `segment_id` is the canonical key for the soft
host segmentation id — prefer it in new code; `user_group_id` is kept
alongside it (same value) since it was already published. Persist this
array as-is in something like `commerce.OrderItem.extra_attributes` at the
moment of checkout. `PriceQuote::fromArray($snapshot)` rebuilds the DTO from
a stored snapshot when you need to re-inspect it later (e.g. for
refunds/audits) — tolerating snapshots stored before
`item_type`/`item_id`/`uom_code`/`segment_id` existed (falling back to the
legacy `user_group_id` key). This package never reads or writes commerce
tables itself.

### QuoteService (without the builder)

`Shop::quotes()` returns the underlying `QuoteService` directly, if you'd
rather call it without the fluent builder (e.g. when you already loaded the
product model):

```php
$quote = Shop::quotes()->resolve($product, userGroupId: 7, tier: 'retail');
```

(`QuoteService::resolve()`'s `$userGroupId` parameter name is kept as
documented public API; the resulting `PriceQuote::$segmentId` is the
canonical property.)

## Existing pricing API (unchanged)

The lower-level resolver keeps working exactly as before — no breaking
changes:

```php
Shop::pricing()->resolve($product, $userObj, 'retail');
```

Two additive methods were added to `ProductPriceResolver` for callers that
don't have (or don't want to assume) a host user object:

```php
Shop::pricing()->resolveForUserGroupId($product, userGroupId: 7, tier: 'retail'); // int
Shop::pricing()->resolveDetailedForUserGroupId($product, userGroupId: 7, tier: 'retail'); // ['price' => int, 'source' => string]
```

`resolve()` is now a thin backward-compatible adapter over
`resolveForUserGroupId()` — same resolve order, same return type, same
signature. Order: user group → explicit tier → default (null-group) window →
`base_price`.

## Existing product/filter services (unchanged)

`Shop::products()` (card pricing/flags) and `Shop::filters()` (category
tree, facets, price range) are untouched. See
[usage/products.md](usage/products.md) and [usage/filters.md](usage/filters.md).

## Validation & exceptions

Builders throw dedicated, catchable exceptions instead of generic
`\Exception` — all under `Karnoweb\Shop\Exceptions`:

| Exception | Thrown by | When |
|---|---|---|
| `ProductNotFoundException` | `Shop::price()->save()`, `Shop::quote()->resolve()` | `productId` missing or does not exist on the configured Product model |
| `InvalidPriceAmountException` | `Shop::price()->save()` | `amount` is negative |
| `InvalidPriceWindowException` | `Shop::price()->save()` | `startsAt` is after `endsAt` |

```php
use Karnoweb\Shop\Exceptions\ProductNotFoundException;

try {
    Shop::price()->productId($missingId)->amount(1000)->save();
} catch (ProductNotFoundException $e) {
    // $e->productId
}
```

## Events

Domain events dispatch **after DB commit** via `ShopEventDispatcher`
(immediately if there is no open transaction).

| Event | Fired by | Payload |
|---|---|---|
| `ProductSaved` | `Shop::product()->create()` | `productId`, `productInterfaceId` |

```php
use Karnoweb\Shop\Events\ProductSaved;

Event::listen(ProductSaved::class, function (ProductSaved $event) {
    // $event->productId, $event->productInterfaceId
    // e.g. invalidate host showcase/category caches here.
});
```

Creating records through the raw Eloquent models (bypassing the builders)
does **not** fire this event automatically — dispatch it yourself via
`ShopEventDispatcher::dispatch(new ProductSaved(...))`. See
[usage/host-integration.md](usage/host-integration.md).

## Extension points (host bridges)

Optional contracts the host may bind — builders and `QuoteService` work
without them, just with reduced behavior:

| Contract | Used by | Effect when bound |
|---|---|---|
| `Contracts\CampaignPriceAdjuster` | `Shop::products()`, `Shop::quote()` / `QuoteService` | Adjusts `finalPrice`/`hasDiscount`/`discountAmount`/`discountPercent`/`campaignId` on top of the resolved `basePrice` |
| `Contracts\StorefrontContext` | `Shop::products()` | Supplies wishlist/cart/compare/rating flags for product cards |

Binding is entirely the host's job (typically in a host service provider);
this package never requires either contract to function.

## What stays in host

Per `SHOP_PACKAGE.md`, this package remains a **catalog domain** package.
The following are explicitly **not** implemented here, even by the new
builders:

- **UI** — no controllers, routes, Livewire, or admin screens.
- **Campaign condition evaluation** — `Campaign`/`CampaignConditionTypeEnum`
  are persistence + enums only; evaluating whether a campaign applies (and
  computing its discount) is the host's `CampaignPriceAdjuster` bridge.
- **Orders, invoices, payments** — owned by `karnoweb/commerce`. `PriceQuote`
  is deliberately a plain array/DTO so commerce/host code can consume it
  without this package depending on commerce, and vice versa.
- **Bundle composition** — `kind('bundle')` is classification only; which
  products make up a bundle, and how it's priced/fulfilled, is host/commerce.
- **Storefront session state** — wishlist/cart/compare/rating live behind
  `StorefrontContext`, bound by the host.
- **SMS, mail, or any other host action** — this package only reads/writes
  its own catalog tables.
- **Tenant/branch resolution** — the host resolves `user_id`/`branch_id`; this
  package never calls `Tenant::current()` or reads request headers.
