# SHOP PACKAGE — Architecture Contract

> Version 2 — Maturity pass. Companion: `karnoweb/commerce` (orders/billing), `karnoweb/translation` (content), `karnoweb/laravel-inventory` (physical stock).

## 0. Non-negotiable principles

1. **The package does not perform host Actions** — no SMS, mail, order creation, accounting posts, or CRM calls.
2. **The package does not import `App\Models\*`** or other Karnoweb domain packages (`crm`, `accounting`, `hr`, `payment`, `inventory`).
3. **Soft host keys only** — `user_id`, `branch_id`, `category_id` without cross-package FK constraints.
4. **Host resolves tenant/branch** — the package never calls `Tenant::current()` or similar.
5. **Output events fire after DB commit** (when introduced) — same pattern as CRM `CrmEventDispatcher`.
6. **Catalog only** — orders, invoices, payments, carts-as-orders live in **commerce** (host today, `karnoweb/commerce` target).

## 1. Package boundary

```text
karnoweb/shop (catalog)
├── ProductInterface, Product, ProductPrice
├── Brand, Attribute*, Campaign (shop marketing)
├── pricing/filter services (wishlist/cart/compare state: host StorefrontContext, no package table)
├── Content via karnoweb/translation (HasTranslation on catalog models)
└── Events: catalog lifecycle (future)

Host application
├── App\Models\ProductInterface extends package model + CMS traits
├── Actions, Livewire admin + web storefront
├── Bridges: Commerce, CRM, Accounting, Inventory
└── karnoweb/commerce: Order, Invoice, Payment
```

**Unit of measure:** use `karnoweb/laravel-inventory` `Uom` / `Inventory::uoms()` — not shop. The legacy `units` table and admin CRUD were retired. `products.default_uom_code` (optional, nullable string) is purely informational metadata for host purchase/sales documents — it is **not** an inventory integration point.

## 1b. Database schema (squashed)

The full catalog schema is a **single migration**:
`database/migrations_squashed/2026_09_04_000000_create_shop_schema.php`.
`ShopServiceProvider::boot()` loads only this path. The old incremental
`2022_01_01_100*` migrations are kept under `database/migrations_legacy/` as
historical reference only — never loaded, never published. Early development,
no production data to preserve, so schema changes go directly into the
squashed file rather than accumulating new incremental migrations.

There is no `user_wishlists` table (or `WishList` model) — wishlist/cart/
compare/rating session state is entirely a host concern behind
`Karnoweb\Shop\Contracts\StorefrontContext`, never a package-owned table.

## 1c. Table prefix & configurable table names

Every table this package owns is resolved through
`Karnoweb\Shop\Support\ShopTables::name($key)` (same pattern as
`karnoweb/laravel-inventory`'s `BaseModel`): it prepends
`config('shop.general.prefix')` (env `SHOP_TABLE_PREFIX`, **default
`"shp_"`**) unless an exact override is set at `config('shop.tables.<key>')`.
`Karnoweb\Shop\Models\BaseModel::getTable()` and the squashed migration both
go through `ShopTables`, so Eloquent and raw schema/query builder calls
always agree on the physical table name. Must be configured **before**
`php artisan migrate` — see `docs/usage.md#table-prefix--configurable-table-names`.

## 2. Host model extension strategy

Lean models live in the package with `Karnoweb\Translation\Concerns\HasTranslation` for translatable fields. The host extends them for:

- `HasCategory`, `HasSeoOption`, `InteractsWithMedia`, `HasTags`, `HasComment`
- `CLogsActivity`, `HasModelCache`
- Application enum casts (`BooleanEnum`, `YesNoEnum`) where UI expects them

## 3. Table naming

See §1b/§1c — every table is prefixed (default `shp_...`) or exactly
overridable via `Karnoweb\Shop\Support\ShopTables`. Existing pre-13.4
deployments that relied on unprefixed catalog tables (`brands`, `products`)
should set `SHOP_TABLE_PREFIX=` (empty) before migrating to keep those names.

## 4. Morph map

Config `shop.morph_map` registers aliases via `ShopMorphMap::register()` with **merge**, not enforce.

Commerce `OrderItem.itemable` must use config `shop.models.product` for product backfill — not hardcoded `App\Models\Product`.

## 5. Pricing

`product_prices` supports time-windowed prices with two portable scoping strategies:

| Strategy | Column | When to use |
|----------|--------|-------------|
| **Tier** | `tier` (string, e.g. `retail`, `wholesale`) | Greenfield / projects without `UserGroup` |
| **Segment** | `segment_id` (soft key; renamed from `user_group_id`) | This Karno host (`UserGroupSeeder` retail/wholesale) |

`ProductPriceBuilder::segmentId()`/`QuoteBuilder::segmentId()` are the
canonical builder methods; `userGroupId()` remains as an alias on both.
`ProductPriceResolver`'s public method/parameter names (`resolveForUserGroupId`,
etc.) are unchanged — only the underlying `segment_id` column was renamed.

`ProductPriceResolver` order: segment → explicit tier → default (null segment) → `base_price`.

Campaign adjustments remain host-bridged via `CampaignPriceAdjuster` contract.

## 6. Integration boundaries

| Domain | Integration |
|--------|-------------|
| **Commerce** | Consumes shop sellables via morph; owns Order/Invoice |
| **Inventory** | `HasInventory` on host Product; shop `stock` column is **legacy/deprecated** |
| **Translation** | `karnoweb/translation` on Brand, ProductInterface, Attribute* |
| **CRM** | No product/order creation in CRM package |
| **Accounting** | Via commerce invoice bridge in host |

## 7. Facade (`Shop`)

Mirrors CRM pattern with **Macroable** extension:

- `Shop::products()` → `ProductService`
- `Shop::filters()` → `ProductFilterService`
- `Shop::pricing()` → `ProductPriceResolver`
- `Shop::config('…')` → shop config
- `Shop::model('product')` → configured model class (single resolution path for relations)
- `Shop::macro('featuredSkus', fn () => …)` → host-specific helpers without forking
- `Shop::brand()/productInterface()/product()/price()` → fresh fluent builders (`src/Builders/`) that create via `config('shop.models.*')`, never a hardcoded class. `productInterface()->kind()` sets the generic, inventory-agnostic business classification (`ProductKindEnum`: physical|service|digital|bundle); `brand()/productInterface()/product()` all accept `->extra(array $attributes)` for the structured `extra_attributes` JSON column.
- `Shop::quote()`/`Shop::quotes()` → `QuoteBuilder`/`QuoteService` produce a portable `PriceQuote` DTO (`src/DTOs/`) for checkout handoff — no commerce dependency in either direction. The DTO/`toCommerceSnapshot()` are deliberately generic (`itemType`/`itemId`, currently always `"shop.product"`/`productId`) so Commerce can store a "sellable snapshot" without this package assuming "product" is the only sellable type it will ever quote; `productId` is kept for backward compatibility. `QuoteBuilder::itemId()`/`itemType()` are aliases/labels over the existing `productId()`. See `docs/usage.md`.

Storefront session state (wishlist, cart, compare, ratings) is **not** in the catalog core. Host binds `Karnoweb\Shop\Contracts\StorefrontContext` (see `HostStorefrontContext`).

## 7b. Events

`ShopEventDispatcher` mirrors commerce: lean catalog events (e.g. `ProductSaved`) dispatch after DB commit. Host cache invalidation should listen to these instead of model `boot()` when possible.

## 8. Inventory / stock (deferred)

The package still exposes `products.stock`, `scopeInStock()`, and legacy stock accessors for backward compatibility. **Do not use in new code.**

Roadmap (not this pass):

1. Host admin/import/export reads/writes inventory via `Inventory::stock()` instead of `products.stock`
2. Dual-write on order paid / cart reserve
3. Drop `stock` column from products after migration period

Host `App\Models\Product::getRemainingStockAttribute()` already dual-reads inventory when installed.

## 9. Extraction phases

| Phase | Content |
|-------|---------|
| 0–4 | Scaffold, catalog models, services, commerce package ✅ |
| 5 | Translation package + wired into catalog models ✅ |
| 6 | Shop facade service delegation ✅ |
| 7 | Unit retired → inventory Uom ✅ |
| 8 | Pricing `tier` column ✅ |
| 9 | Extensibility pass: Macroable, `model()`, StorefrontContext, ShopEventDispatcher ✅ |
| 10 | Inventory dual-write + stock column removal ⏳ deferred |
| 11 | Accounting-like builder surface: `Shop::brand()/productInterface()/product()/price()/quote()`, `PriceQuote` DTO, `QuoteService` ✅ |
| 12 | Generic product `kind` (physical/service/digital/bundle), `extra_attributes` on `Product`, refined `PriceQuote` (`discountAmount`, `*_price` source strings) ✅ |
| 13 | Squashed schema (`database/migrations_squashed`, legacy retained as reference only), generic `PriceQuote`/snapshot (`itemType`/`itemId`), `products.default_uom_code` ✅ |
| 14 | Table prefix + configurable table names (`ShopTables`, default `shp_`), `user_wishlists`/`WishList` removed entirely, `product_prices.user_group_id` → `segment_id`, `campaigns.discount_id` → `external_discount_id`, `campaign_type` default → `price_adjustment`, `product_interfaces.category_id` nullable ✅ |

## 10. What must NOT move into shop

- Order, OrderItem, Invoice, Payment, Wallet, Discount (commerce)
- Warehouse, stock movements, Uom (inventory)
- Lead, Deal (crm)
- Accounting documents
- Admin menu, permissions, module-manager config

## 11. Architecture tests

Run: `cd packages/karnoweb/shop && composer install && vendor/bin/phpunit`
