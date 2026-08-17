<?php

return [

    // Base URL of the VMOS Cloud OpenAPI.
    'base_url' => env('VMOS_BASE_URL', 'https://api.vmoscloud.com'),

    // Access Key ID / Secret Access Key from VMOSCloud console -> Developer -> API.
    'access_key' => env('VMOS_ACCESS_KEY'),
    'secret_key' => env('VMOS_SECRET_KEY'),

    // Seconds to wait for the TCP/TLS handshake, then for the reply. A shared
    // host that can't route to VMOS fails on the first one, not the second.
    'connect_timeout' => (int) env('VMOS_CONNECT_TIMEOUT', 15),
    'timeout' => (int) env('VMOS_TIMEOUT', 30),

    // Force IPv4. Harmless when the host has no IPv6, and avoids the stall on
    // hosts that advertise an IPv6 route they can't actually use.
    'force_ipv4' => (bool) env('VMOS_FORCE_IPV4', true),

    // Signalling endpoint the Web H5 SDK connects to for the live screen. This
    // is NOT the OpenAPI host — it's the streaming gateway, and the region can
    // differ per account. If the live screen fails to connect while the rest of
    // the API works, this is the value to check with VMOS support.
    'sdk_base_url' => env('VMOS_SDK_BASE_URL', 'https://openapi-hk.armcloud.net'),

    // Public URL VMOS should call back on task/instance events (routes/api.php).
    'callback_url' => env('VMOS_CALLBACK_URL'),

    // Shared secret appended as ?token=... to the callback URL configured in the
    // VMOS console, since VMOS callbacks are not otherwise signed/authenticated.
    'webhook_token' => env('VMOS_WEBHOOK_TOKEN'),

];
