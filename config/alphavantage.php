<?php

return [
    'base_url'   => env('ALPHAVANTAGE_BASE_URL', '"https://www.alphavantage.co/query?'),
    'api_key'    => env('ALPHAVANTAGE_API_KEY', ''),
    'rate_limit' => env('ALPHAVANTAGE_RATE_LIMIT', 5),
];
