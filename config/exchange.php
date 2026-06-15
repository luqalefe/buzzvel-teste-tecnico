<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Exchange-rate provider
    |--------------------------------------------------------------------------
    |
    | exchangerate-api.com is an EUR-capable provider; the API key is part of
    | the request path. `source` is stored verbatim on every payment request so
    | the origin of a rate stays auditable forever. The provider is fully
    | mocked in tests, so it can be swapped without touching the suite.
    |
    */

    'provider' => [
        'source' => env('EXCHANGE_RATE_SOURCE', 'exchangerate-api.com'),
        'base_url' => env('EXCHANGE_PROVIDER_URL', 'https://v6.exchangerate-api.com/v6'),
        'key' => env('EXCHANGE_API_KEY'),
        'timeout' => (int) env('EXCHANGE_PROVIDER_TIMEOUT', 5),
    ],

];
