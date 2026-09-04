# سلسله‌مراتب کاتالوگ

```text
Brand / Category (میزبان)
    └── ProductInterface   ← ورودی کاتالوگ (عنوان، اسلاگ، نوع)
            └── Product    ← SKU قابل فروش (قیمت پایه، ابعاد، …)
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

`simple`، `codding`، `digital`، `service` — از `ProductInterfaceTypeEnum`.

## ویژگی‌ها

`AttributeTypeEnum`: `text`، `color`، `select`، `number`، `checkbox`.

فیلتر ویترین معمولاً روی مقادیر انتخاب‌شده (AND بین attributeها) اعمال می‌شود — جزئیات در [usage/filters.md](../usage/filters.md).
