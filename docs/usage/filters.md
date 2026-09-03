# فیلتر و facet

```php
use Karnoweb\Shop\Facades\Shop;
use Karnoweb\Shop\Models\Product;

$filters = Shop::filters();

$tree = $filters->getCategoryTree();
$childIds = $filters->descendantIds($categoryId);

$query = Product::query()->active();
$filters->applyAttributeFilter($query, $selectedAttributeValueIds);

$brands = $filters->brandsWithCounts();
$attrs  = $filters->filterableAttributes($categoryIds);
$range  = $filters->priceRange($user?->profile?->user_group_id ?? null);
```

## قوانین

- فیلتر ویژگی‌ها بین attributeهای مختلف معمولاً AND است (محصول باید همهٔ انتخاب‌ها را پوشش دهد).
- درخت دسته و شمارنده‌ها ممکن است کش شوند؛ بعد از تغییر بزرگ کاتالوگ کش مربوط را در میزبان باطل کنید.
- Category از مدل config (`shop.models.category`) می‌آید — پکیج جدول category نمی‌سازد.

## خطاها

ورودی خالی معمولاً یعنی بدون فیلتر اضافی؛ استثنای دامنهٔ اختصاصی تعریف نشده است.

## نتیجه ذخیره‌شده

متدهای فیلتر کوئری را تغییر می‌دهند یا دادهٔ UI برمی‌گردانند؛ جدول جدیدی نمی‌نویسند.
