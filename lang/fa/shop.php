<?php

declare(strict_types=1);

return [
    'attribute' => [
        'type' => [
            'text' => 'متن',
            'color' => 'رنگ',
            'select' => 'انتخابی',
            'number' => 'عدد',
            'checkbox' => 'چک‌باکس',
        ],
    ],
    'product_interface' => [
        'type' => [
            'simple' => 'ساده',
            'codding' => 'تنوع‌دار',
        ],
        'kind' => [
            'simple' => 'ساده',
            'ingredient' => 'ماده اولیه',
            'composed' => 'ترکیبی',
            'virtual' => 'مجازی',
            'bundle' => 'باندل',
        ],
    ],
    'campaign' => [
        'type' => [
            'product_based' => 'مبتنی بر محصول',
            'order_based' => 'مبتنی بر سفارش',
            'price_adjustment' => 'تعدیل قیمت',
        ],
        'condition' => [
            'category' => 'دسته‌بندی',
            'brand' => 'برند',
            'product' => 'محصول',
            'user' => 'کاربر',
            'user_group' => 'گروه کاربری',
            'min_order_amount' => 'حداقل مبلغ سفارش',
            'min_order_count' => 'حداقل تعداد سفارش',
            'first_order' => 'اولین سفارش',
            'date_range' => 'بازه زمانی',
        ],
    ],
    'exceptions' => [
        'invalid_price_window' => 'بازهٔ قیمت نامعتبر است: starts_at باید قبل یا برابر ends_at باشد.',
        'invalid_price_amount' => 'مقدار قیمت باید صفر یا بیشتر باشد.',
        'product_not_found' => 'محصول [:id] یافت نشد.',
        'product_interface_not_found' => 'واسط محصول [:id] یافت نشد.',
    ],
];
