# Karnoweb Shop

پکیج دامنهٔ **کاتالوگ** برای لاراول: محصول، برند، ویژگی، قیمت‌گذاری پنجره‌ای، کمپین فروشگاهی و علاقه‌مندی.

**مستندات:** [docs/README.md](docs/README.md) — [مفاهیم](docs/concepts/README.md) و [طرز استفاده](docs/usage/README.md)  
قرارداد معماری (انگلیسی): [SHOP_PACKAGE.md](SHOP_PACKAGE.md)

## Requirements

- PHP 8.3+
- Laravel 13.x
- `karnoweb/translation` ^13.0

## Installation

```bash
composer require karnoweb/shop:^13.0
php artisan vendor:publish --tag=shop-config
php artisan vendor:publish --tag=shop-lang   # اختیاری
php artisan migrate
```

## قابلیت‌ها (v13.0)

از فاساد `Shop` برای سرویس‌های کاتالوگ استفاده کنید.

| حوزه | دسترسی | نقش |
|------|--------|------|
| قیمت | `Shop::pricing()` | resolve قیمت عددی |
| کارت محصول | `Shop::products()` | قیمت + فلگ‌های ویترین |
| فیلتر | `Shop::filters()` | دسته، برند، ویژگی، بازه قیمت |
| مدل‌ها | `Shop::model()` | کلاس مدل از config |

**در پکیج نیست:** سفارش/فاکتور/پرداخت (`karnoweb/commerce`)، UI ادمین، سبد خرید session، انبار حرکتی (`karnoweb/laravel-inventory`).

## مثال سریع

```php
use Karnoweb\Shop\Facades\Shop;

$price = Shop::pricing()->resolve($product, auth()->user());
$cards = Shop::products()->resolveForProducts($products);
$tree  = Shop::filters()->getCategoryTree();
```

بیشتر: [docs/usage/README.md](docs/usage/README.md)

## License

MIT
