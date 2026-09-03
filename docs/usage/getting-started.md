# نصب و راه‌اندازی

```bash
composer require karnoweb/shop:^13.0
php artisan vendor:publish --tag=shop-config
php artisan vendor:publish --tag=shop-migrations   # الزامی
php artisan vendor:publish --tag=shop-lang         # اختیاری
php artisan migrate
```

مایگریشن‌ها با تاریخ ثابت `2022_01_01_100*` پابلیش می‌شوند تا زود اجرا شوند و نام فایل‌ها پایدار بماند. جداول پیش‌فرض بدون پیشوندند (`products`, `brands`, …)؛ در صورت نیاز:

```env
SHOP_TABLE_PREFIX=
SHOP_CURRENCY=IRR
SHOP_USER_KEY_TYPE=int
SHOP_BRANCH_KEY_TYPE=int
```

مدل‌ها را به subclass میزبان اشاره دهید (پیش‌فرضهای config همین الگوی `App\Models\…` را دارند):

```env
SHOP_PRODUCT_MODEL=App\Models\Product
SHOP_PRODUCT_INTERFACE_MODEL=App\Models\ProductInterface
SHOP_BRAND_MODEL=App\Models\Brand
# … سایر SHOP_*_MODEL
```

## فاساد

```php
use Karnoweb\Shop\Facades\Shop;

Shop::config('pricing.currency');
Shop::model('product');      // class-string
Shop::newModel('brand');
Shop::pricing();             // ProductPriceResolver
Shop::products();            // ProductService
Shop::filters();             // ProductFilterService

Shop::macro('featured', fn () => Shop::model('product')::query()->main()->limit(8)->get());
```

## قوانین

- قبل از migrate، جداول وابستهٔ میزبان مثل `users` / `categories` (در صورت FK) باید وجود داشته باشند یا مایگریشن‌ها با ترتیب پروژه سازگار شوند.
- ترجمهٔ فیلدهای Brand و ProductInterface از `karnoweb/translation` می‌آید؛ آن پکیج را هم نصب و migrate کنید.

## خطاها

خطاهای نصب معمولاً migration/config لاراول‌اند. مدل نامعتبر در `Shop::model()` استثنا می‌دهد.

## نتیجه ذخیره‌شده

جداول کاتالوگ، pivot ویژگی‌ها، `product_prices`، `campaigns`، `user_wishlists` و morph mapهای `shop_*` در boot ثبت می‌شوند.
