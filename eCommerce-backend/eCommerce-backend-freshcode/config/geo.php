<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Countries without postal / ZIP codes
    |--------------------------------------------------------------------------
    |
    | ISO2 codes of countries that do not use postal codes. Addresses in these
    | countries are city/state driven, so the `zipcode` field is optional for
    | them (required everywhere else). This is stable reference data — adjust
    | the list if a country's postal system changes.
    |
    */

    'no_postcode_countries' => [
        'AO', 'AG', 'AW', 'BS', 'BZ', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CF', 'KM',
        'CG', 'CD', 'CK', 'CI', 'DJ', 'DM', 'GQ', 'ER', 'FJ', 'TF', 'GM', 'GH',
        'GD', 'GN', 'GY', 'HK', 'IE', 'KI', 'KP', 'LY', 'MO', 'MW', 'ML', 'MR',
        'NR', 'AN', 'NU', 'PA', 'QA', 'RW', 'KN', 'ST', 'SC', 'SL', 'SB', 'SO',
        'SR', 'SY', 'TZ', 'TL', 'TG', 'TK', 'TO', 'TV', 'UG', 'AE', 'VU', 'YE',
        'ZW',
    ],

];
