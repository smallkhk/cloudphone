<?php

return [

    // Base URL of the VMOS Cloud OpenAPI.
    'base_url' => env('VMOS_BASE_URL', 'https://api.vmoscloud.com'),

    // Access Key ID / Secret Access Key from VMOSCloud console -> Developer -> API.
    'access_key' => env('VMOS_ACCESS_KEY'),
    'secret_key' => env('VMOS_SECRET_KEY'),

    // Public URL VMOS should call back on task/instance events (routes/api.php).
    'callback_url' => env('VMOS_CALLBACK_URL'),

    // Shared secret appended as ?token=... to the callback URL configured in the
    // VMOS console, since VMOS callbacks are not otherwise signed/authenticated.
    'webhook_token' => env('VMOS_WEBHOOK_TOKEN'),

];
