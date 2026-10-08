<?php

return [

    // Wallet address customers pay USDT (TRC20) to. A single shared address is the
    // simplest model for a solo reseller; payments are matched by tx hash + amount.
    'usdt_trc20_address' => env('CRYPTO_USDT_TRC20_ADDRESS'),

    // Official USDT TRC20 contract on Tron mainnet.
    'usdt_trc20_contract' => env('CRYPTO_USDT_TRC20_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),

    'trongrid_base_url' => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
    'trongrid_api_key' => env('TRONGRID_API_KEY'), // optional, raises TronGrid's rate limit

    // Wallet address customers pay USDT (BEP20 / BNB Smart Chain) to.
    'usdt_bep20_address' => env('CRYPTO_USDT_BEP20_ADDRESS'),

    // Official USDT BEP20 contract on BNB Smart Chain mainnet.
    'usdt_bep20_contract' => env('CRYPTO_USDT_BEP20_CONTRACT', '0x55d398326f99059fF775485246999027B3197955'),

    // A public BSC RPC node — no API key needed, unlike BscScan's API.
    'bsc_rpc_url' => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org'),

    // How long a customer has to pay before the quote expires and must be recreated.
    'payment_window_minutes' => (int) env('CRYPTO_PAYMENT_WINDOW_MINUTES', 30),

    // Accept slightly underpaid amounts (network fee rounding, etc.).
    'amount_tolerance_percent' => (float) env('CRYPTO_AMOUNT_TOLERANCE_PERCENT', 0.5),

];
