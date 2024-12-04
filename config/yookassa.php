<?php

return [
    'shop_id' => env('YOO_SHOP_ID'),
    'secret_key' => env('YOO_SECRET_KEY'),
'success_url' => env('APP_URL') . env('YOO_SUCCESS_URL', '/payment/success'),
    'fail_url' => env('APP_URL') . env('YOO_FAIL_URL', '/payment/fail'),
];

