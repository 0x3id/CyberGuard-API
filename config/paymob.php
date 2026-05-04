<?php

return [

    'base_url' => rtrim(env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'), '/'),

    'api_key' => env('PAYMOB_API_KEY'),

    'integration_id' => env('PAYMOB_INTEGRATION_ID'),

    'iframe_id' => env('PAYMOB_IFRAME_ID'),

    'hmac_secret' => env('PAYMOB_HMAC_SECRET'),

    'billing_period_months' => (int) env('PAYMOB_BILLING_PERIOD_MONTHS', 1),

];
