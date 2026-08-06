<?php

return [

    // Wallet address customers pay USDT (TRC20) to. A single shared address is the
    // simplest model for a solo reseller; payments are matched by tx hash + amount.
    'usdt_trc20_address' => env('CRYPTO_USDT_TRC20_ADDRESS'),

    // Official USDT TRC20 contract on Tron mainnet.
    'usdt_trc20_contract' => env('CRYPTO_USDT_TRC20_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),

    'trongrid_base_url' => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
    'trongrid_api_key' => env('TRONGRID_API_KEY'), // optional, raises TronGrid's rate limit

    // How long a customer has to pay before the quote expires and must be recreated.
    'payment_window_minutes' => (int) env('CRYPTO_PAYMENT_WINDOW_MINUTES', 30),

    // Accept slightly underpaid amounts (network fee rounding, etc.).
    'amount_tolerance_percent' => (float) env('CRYPTO_AMOUNT_TOLERANCE_PERCENT', 0.5),

];
