<?php

return [
    'company' => [
        'name' => env('ECLISE_COMPANY_NAME', 'Eclise Technology Inc.'),
        'tagline' => 'Repair. Reuse. Reconnect.',
        'description' => 'Professional device repair, replacement parts, new and pre-owned technology products, and customer repair tracking.',
    ],

    'contact' => [
        'email' => env('ECLISE_CONTACT_EMAIL'),
        'phone' => env('ECLISE_CONTACT_PHONE'),
        'address' => env('ECLISE_CONTACT_ADDRESS'),
        'hours' => env('ECLISE_CONTACT_HOURS'),
        'social_links' => [
            'facebook' => env('ECLISE_SOCIAL_FACEBOOK'),
            'instagram' => env('ECLISE_SOCIAL_INSTAGRAM'),
            'linkedin' => env('ECLISE_SOCIAL_LINKEDIN'),
        ],
    ],

    'enquiry_types' => [
        'repair' => 'Repair',
        'repair_quote' => 'Repair Quote',
        'repair_booking' => 'Repair Booking',
        'parts' => 'Parts',
        'shop_product' => 'Shop Product',
        'existing_order' => 'Existing Order',
        'existing_repair' => 'Existing Repair',
        'general' => 'General Enquiry',
    ],
];
