<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live LTC/USD price from CoinGecko's public `simple/price` endpoint — no API
 * key needed, unlike BscScan. Unlike USDT, LTC isn't pegged to $1, so an order
 * or deposit's USD total has to be converted at quote time.
 *
 * Cached briefly so a burst of checkouts doesn't hammer CoinGecko with one
 * request each — the price barely moves within a minute anyway.
 */
class LtcPriceFeed
{
    public function usdPrice(): float
    {
        return Cache::remember('ltc_usd_price', 60, function () {
            $response = Http::timeout(10)->get(rtrim(config('crypto.coingecko_base_url'), '/').'/simple/price', [
                'ids' => 'litecoin',
                'vs_currencies' => 'usd',
            ]);

            $price = $response->json('litecoin.usd');

            if (! $response->successful() || ! $price) {
                throw new RuntimeException('Could not fetch the current LTC price — please try again shortly.');
            }

            return (float) $price;
        });
    }
}
