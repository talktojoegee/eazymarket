<?php

return [
    'sources' => [
        'web' => resource_path('language-sources/web/en.json'),
        'customer_app' => resource_path('language-sources/customer_app/en.json'),
        'seller_app' => resource_path('language-sources/seller_app/en.json'),
    ],
    'quick_add' => [
        'en' => ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr'],
        'es' => ['name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr'],
        'fr' => ['name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr'],
        'de' => ['name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr'],
        'ar' => ['name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl'],
    ],
];
