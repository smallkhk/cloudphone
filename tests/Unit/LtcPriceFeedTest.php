<?php

namespace Tests\Unit;

use App\Services\Payments\LtcPriceFeed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class LtcPriceFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('ltc_usd_price');
    }

    #[Test]
    public function it_returns_the_live_usd_price(): void
    {
        Http::fake(['*coingecko.com*' => Http::response(['litecoin' => ['usd' => 65.43]])]);

        $this->assertSame(65.43, (new LtcPriceFeed)->usdPrice());
    }

    #[Test]
    public function it_caches_the_price_instead_of_calling_every_time(): void
    {
        Http::fake(['*coingecko.com*' => Http::response(['litecoin' => ['usd' => 70.0]])]);

        $feed = new LtcPriceFeed;
        $feed->usdPrice();
        $feed->usdPrice();

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_throws_when_the_price_feed_is_unavailable(): void
    {
        Http::fake(['*coingecko.com*' => Http::response([], 500)]);

        $this->expectException(RuntimeException::class);

        (new LtcPriceFeed)->usdPrice();
    }
}
