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
- [Product kinds (inventory / sell behavior)](#product-kinds-inventory--sell-behavior)
- [Variant modeling: ProductInterface vs Product](#variant-modeling-productinterface-vs-product)
- [CODDING axes: preview and safe sync](#codding-axes-preview-and-safe-sync)
- [Locking products](#locking-products)
- [Branch scoping](#branch-scoping)
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
| Bulk prices | `Shop::prices()` | `Builders\BulkProductPriceBuilder` |
| Variants | `Shop::variants()` | `Builders\VariantsBuilder` |
| Catalog context | `Shop::context()` | `Support\ShopContext` |
| Quote reader | `Shop::quote()` | `Builders\QuoteBuilder` |
| Quote service | `Shop::quotes()` | `Services\QuoteService` |
| Pricing (existing) | `Shop::pricing()` | `Services\ProductPriceResolver` |
| Product cards (existing) | `Shop::products()` | `Services\ProductService` |
| Filters (existing) | `Shop::filters()` | `Services\ProductFilterService` |

Every builder call (`brand()`, `productInterface()`, `product()`, `price()`,
`prices()`, `variants()`, `quote()`) returns a **fresh, isolated instance** —
nothing is shared or reset between calls, so builders are safe to reuse in
loops without leaking state.

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
    ->type('simple')          // simple|codding — variant structure only
    ->kind('simple')          // simple|ingredient|composed|virtual|bundle
    ->brandId($brand->id)
    ->categoryId(10)          // soft host key, nullable
    ->branchId(null)          // null = global catalog
    ->sku('COF-1KG')          // optional; auto-generated from the slug when omitted
    ->basePrice(1_200_000)
    ->defaultUomCode('kg')
    ->productPublished(true)  // main Product is unpublished unless set
    ->published(true)
    ->create();

$product = $pi->mainProduct;  // always created in the same transaction

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
| `Shop::productInterface()` | `slug()` (unique) | Model configured at `shop.models.product_interface`, with `mainProduct` loaded |
| `Shop::product()` | `productInterfaceId()` | Model configured at `shop.models.product` (extra SKUs; the main SKU is created by the interface builder) |

All three also accept `attribute($key, $value)` (single raw attribute),
`extra(array $attributes)` (structured JSON extension point — see below),
and `fill(array $attributes)` (bulk) as escape hatches for host-specific
columns without waiting on a new builder method.

`categoryId()` and `brandId()` are **soft host keys**: plain integers/ids,
stored without `->constrained()`/`->foreign()`. The host owns the `categories`
table (or equivalent); this package never creates or reads it directly.

## Product kinds (inventory / sell behavior)

`ProductInterface.kind` is inventory/sell behavior. It is independent from
`type`, which is **only** the variant structure (`simple` or `codding`):

| Kind | Meaning |
|---|---|
| `simple` (default) | A regular sellable item |
| `ingredient` | Used as an input of a composed item |
| `composed` | Built from ingredients / other catalog items |
| `virtual` | Non-physical (license, booking slot, download, ...) |
| `bundle` | A kit of other products (composition stays in the host) |

```php
use Karnoweb\Shop\Enums\ProductKindEnum;
use Karnoweb\Shop\Enums\ProductInterfaceTypeEnum;

Shop::productInterface()->slug('consulting-hour')->kind(ProductKindEnum::VIRTUAL)->create();
Shop::productInterface()->slug('t-shirt')->type(ProductInterfaceTypeEnum::CODDING)->kind('simple')->create();
```

`kind` and `type` accept a plain string or the matching enum. The database
stores the string value. Builders accept both.

## Variant modeling: ProductInterface vs Product

- **`ProductInterface`** — the parent: shared, translatable attributes
  (`title`, `description`, `body`), `type`, `kind`, `brand_id`, `category_id`,
  publishing flags. One row per "product family".
- **`Product`** — a variant/SKU: `sku`, `base_price`, `is_main`, and
  variant-specific attribute values. One or more rows per `ProductInterface`
  (`ProductInterface::hasMany(Product::class)` / `Product::belongsTo(ProductInterface::class)`).

Creating a `ProductInterface` **always** creates exactly one `Product` with
`is_main=true` in the same DB transaction — for every type, including
`codding`. The main product inherits `branch_id` and `default_uom_code`,
gets an auto-generated SKU when you omit `sku()`, and is unpublished
unless you call `productPublished(true)`. After commit, `ProductSaved` is
dispatched via `ShopEventDispatcher`. The returned interface has
`mainProduct` loaded.

```php
$pi = Shop::productInterface()->slug('t-shirt')->type('codding')->kind('simple')->categoryId(5)->create();
$pi->mainProduct; // always present

// Extra SKUs can still be created by hand when you are not using axis sync:
Shop::product()->productInterfaceId($pi->id)->sku('TSHIRT-L')->basePrice(420_000)->create();
```

On the edit page the host typically: load category-based attributes → let
the operator pick coding axes → `preview()` → `sync('safe')`.

Pricing, price windows, and quotes are always resolved **per `Product`**
(per SKU/variant), never per `ProductInterface` — see
[Price records](#price-records-productprice) below.

## CODDING axes: preview and safe sync

`Shop::variants()` computes the cartesian product of `coding=true` axes.
`preview()` never writes. `sync('safe')` creates missing variant products
and never deletes anything.

```php
$axes = [
    $colorAttributeId => ['coding' => true, 'values' => [$redId, $blueId]],
    $sizeAttributeId => ['coding' => true, 'values' => [$sId, $mId]],
    $materialAttributeId => ['coding' => false, 'values' => [$cottonId]],
];

$preview = Shop::variants()
    ->forProductInterface($pi->id)
    ->codingAxes($axes)
    ->preview();
// 2×2 = 4 signatures, each with sorted value ids + a stable suggested SKU

$result = Shop::variants()
    ->forProductInterface($pi->id)
    ->codingAxes($axes)
    ->sync('safe');
```

Rules:

- Only `coding=true` axes enter the cartesian product.
- SKU is stable for a given signature: `{pi-slug}-{valueId}-{valueId}`
  (sorted). Bind `SkuGeneratorContract` to override.
- `sync('safe')` creates missing variants, never deletes, and marks
  obsolete unlocked variants as `extra_attributes['suspended']=true`.
- A product with `locked_at` set is never auto-changed or suspended.
- When the axes hash changes, `product_interfaces.variants_status`
  becomes `needs_sync`. After a successful sync it is `ready` and
  `variants_hash` is stored.
- SIMPLE interfaces store at most one value per attribute on
  `product_interface_attribute_values`. Coding-axis values live on the
  variant `product_attribute_values` rows.

## Locking products

Locking is initiated by the **host** (for example after a sale is
confirmed in another package). This package never watches orders.

```php
Shop::products()->lock($productId, reason: 'sold', lockedBy: $actorId);
```

Locked products keep their SKU and stay in the catalog. Variant sync
skips them.

## Branch scoping

`branch_id` is a nullable soft host key on `product_interfaces`,
`products`, `product_prices`, and `campaigns`. `null` means **global**.

```env
SHOP_CATALOG_BRANCH_MODE=inherit_global
```

| Mode | `forBranch(X)` |
|---|---|
| `strict` | only `branch_id = X` |
| `inherit_global` (default) | `branch_id` in `(null, X)` |

```php
ProductInterface::query()->forBranch(3)->get();
Product::query()->forBranch(3)->get();
ProductPrice::query()->forBranch(3)->get();
```

If the host binds `BranchContextResolverContract`, builders default to
`Shop::context()->branchId()`. If it is not bound, pass `branchId()`
explicitly (or omit it for a global row).

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
    ->sku('COF-1KG-EXTRA')
    ->basePrice(1_200_000)
    ->weightGrams(1000)
    ->extra(['pack' => 'vacuum'])
    ->create();

$pi->extra_attributes;      // ['origin' => 'brazil', 'roast' => 'medium']
$product->weight_grams;     // 1000
$product->extra_attributes; // ['pack' => 'vacuum']
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
    ->currency('IRR')         // optional; defaults to shop.money.default_currency
    ->tier('retail')
    ->segmentId(null)
    ->amount(1_200_000)       // or ['IRR' => 1_200_000, 'USD' => 12]
    ->save();                 // starts_at/ends_at omitted => non-expiring

Shop::prices()
    ->forProductInterface($pi->id) // or ->forProductIds([1, 2, 3])
    ->tier('retail')
    ->segmentId(null)
    ->currency('IRR')
    ->amount(1_200_000)
    ->saveAll();
```

**Inputs:** `productId()` (required), `currency()`, `tier()`, `segmentId()`
(null = default/segment-less row — a **soft host key**; `userGroupId()` is
kept as an alias), `branchId()`, `amount()` (int, or `currency => amount`
map), `startsAt()` / `endsAt()` (optional; both null = non-expiring).

Resolver match order for a quote: `product_id` + **required currency**,
then branch (exact, else null when `inherit_global`), then segment (exact,
else null), then tier (exact, else null).

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
- **Tenant/branch resolution** — the host may bind
  `BranchContextResolverContract` or pass `branchId()` explicitly. This
  package never calls `Tenant::current()` or reads request headers.
