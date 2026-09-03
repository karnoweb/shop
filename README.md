# Karnoweb Shop Package

Catalog domain package for Karno Base: products, brands, attributes, units, and pricing services.

Follows the same **data + services + events** boundary as `karnoweb/crm`. See `SHOP_PACKAGE.md` for the architecture contract.

## Install (monorepo host)

```bash
composer require karnoweb/shop:^13.0
php artisan vendor:publish --tag=shop-config
php artisan migrate
```

## Scope

| In package | In host application |
|------------|---------------------|
| Catalog models & migrations | Livewire admin + storefront UI |
| Catalog services | Actions & Pipelines |
| Output events (after commit) | Permissions, menu, routes |
| Config & morph map | Bridges to CRM, Commerce, Accounting, Inventory |

**Not in this package:** orders, invoices, payments, wallets — those belong to `karnoweb/commerce` (planned).

## Documentation

- `SHOP_PACKAGE.md` — architecture contract (source of truth)
- `packages/karnoweb/crm/README.md` — reference pattern
