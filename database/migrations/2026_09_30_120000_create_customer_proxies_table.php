<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_proxies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();

            // VMOS's own proxyId from listStaticProxies() — needed for
            // attachProxies()/deleteStaticProxy(). Null until the async
            // purchase finishes and this record gets matched to a real proxy.
            $table->unsignedInteger('vmos_proxy_id')->nullable();
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('account')->nullable(); // VMOS doesn't return a password field on listStaticProxies
            $table->string('country_code', 8)->nullable();

            // Purchase is async on VMOS's side (createProxyOrder → taskId →
            // proxyOrderStatus) — see StandaloneProxyProvisioner.
            $table->string('purchase_client_token')->nullable();
            $table->string('purchase_status')->default('PROCESSING');
            $table->text('purchase_error')->nullable();

            // Which of the customer's own devices this proxy is currently
            // attached to, if any — a customer can move it between their own
            // devices at will, unlike the checkout add-on which is fixed to
            // the device it was bought alongside.
            $table->string('attached_pad_code')->nullable();

            $table->json('raw_payload')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_proxies');
    }
};
