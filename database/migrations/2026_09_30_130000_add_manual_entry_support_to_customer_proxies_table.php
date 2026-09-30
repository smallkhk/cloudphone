<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_proxies', function (Blueprint $table) {
            // 'vmos' (bought through us, async purchase/poll/match) or
            // 'custom' (the customer's own proxy, added directly — no order,
            // no VMOS purchase, delivered immediately, same as the checkout
            // add-on's "Use my own proxy" mode).
            $table->string('source')->default('vmos')->after('sku_id');

            // VMOS's listStaticProxies() doesn't return one for a bought
            // proxy, but a customer's own proxy needs one to connect.
            $table->string('password')->nullable()->after('account');
            $table->string('proxy_name')->default('socks5')->after('password'); // socks5 | http-relay
            $table->string('proxy_type')->default('proxy')->after('proxy_name'); // proxy | vpn

            // Nullable because a manually-added ('custom') proxy has no
            // purchase behind it — no Order, no Sku.
            $table->foreignId('order_id')->nullable()->change();
            $table->foreignId('sku_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_proxies', function (Blueprint $table) {
            $table->dropColumn(['source', 'password', 'proxy_name', 'proxy_type']);
            $table->foreignId('order_id')->nullable(false)->change();
            $table->foreignId('sku_id')->nullable(false)->change();
        });
    }
};
