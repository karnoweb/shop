<?php

declare(strict_types=1);

return [
    'attribute' => [
        'type' => [
            'text' => 'Text',
            'color' => 'Color',
            'select' => 'Select',
            'number' => 'Number',
            'checkbox' => 'Checkbox',
        ],
    ],
    'product_interface' => [
        'type' => [
            'simple' => 'Simple',
            'codding' => 'Variant',
            'digital' => 'Digital',
            'service' => 'Service',
        ],
    ],
    'campaign' => [
        'type' => [
            'product_based' => 'Product-based',
            'order_based' => 'Order-based',
        ],
        'condition' => [
            'category' => 'Category',
            'brand' => 'Brand',
            'product' => 'Product',
            'user' => 'User',
            'user_group' => 'User group',
            'min_order_amount' => 'Minimum order amount',
            'min_order_count' => 'Minimum order count',
            'first_order' => 'First order',
            'date_range' => 'Date range',
        ],
    ],
];
