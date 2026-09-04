# سلسله‌مراتب کاتالوگ

```text
Brand / Category (میزبان)
    └── ProductInterface   ← ورودی کاتالوگ (عنوان، اسلاگ، نوع)
            └── Product    ← SKU قابل فروش (قیمت پایه، وزن، …)
                    └── ProductPrice  ← قیمت پنجره‌ای (گروه کاربری / tier)
```

## نقش‌ها

| مدل | نقش |
|------|------|
| **ProductInterface** | والد منطقی محصول در ویترین؛ می‌تواند چند Product (واریانت) داشته باشد |
| **Product** | واحد فروش؛ `is_main` برای واریانت اصلی |
| **Brand** | برند؛ فیلدهای متنی با `HasTranslation` |
| **Attribute*** | تعریف ویژگی، گروه، مقدار؛ اتصال به محصول و دسته |

> علاقه‌مندی (wishlist) دیگر جدول مستقل پکیج نیست — کاملاً از طریق قرارداد میزبان
> `StorefrontContext` مدیریت می‌شود (بدون جدول `user_wishlists`).

## انواع ProductInterface

ساختار واریانت (`type`): `simple`، `codding` — از `ProductInterfaceTypeEnum`.

رفتار فروش/موجودی (`kind`): `simple`، `ingredient`، `composed`، `virtual`، `bundle` — از `ProductKindEnum`.

ایجاد `ProductInterface` همیشه یک `Product` با `is_main=true` می‌سازد. برای `codding` از `Shop::variants()->preview()` / `sync('safe')` استفاده کنید. `branch_id` اختیاری است (`null` = کاتالوگ سراسری).

## ویژگی‌ها

`AttributeTypeEnum`: `text`، `color`، `select`، `number`، `checkbox`.

فیلتر ویترین معمولاً روی مقادیر انتخاب‌شده (AND بین attributeها) اعمال می‌شود — جزئیات در [usage/filters.md](../usage/filters.md).
