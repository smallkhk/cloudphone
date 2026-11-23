<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\CloudInstance;
use App\Models\CloudNumber;
use App\Models\CryptoPayment;
use App\Models\CustomerProxy;
use App\Models\EmailAccount;
use App\Models\InstanceTask;
use App\Models\Order;
use App\Models\PhoneNumber;
use App\Models\Sku;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletTransaction;
use App\Services\Vmos\VmosRegionCatalog;

/**
 * The JSON shapes the iPhone app reads. Kept in one place so a field the app
 * depends on can't silently change shape in one endpoint but not another.
 * Nothing here exposes upstream provider names or raw provider payloads.
 */
class MobileResources
{
    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => (float) $user->balance,
            'is_admin' => (bool) $user->is_admin,
        ];
    }

    public static function device(CloudInstance $i): array
    {
        return [
            'id' => $i->id,
            'name' => $i->nickname ?: ($i->sku?->name ?? 'Cloud Phone'),
            'pad_code' => $i->pad_code,
            'ready' => (bool) $i->pad_code,
            'status' => $i->statusLabel(),
            'online' => (bool) $i->online,
            'android_version' => $i->sku?->android_version,
            'model' => $i->sku?->config_model,
            'screenshot_url' => $i->screenshot_url,
            'auto_renew' => (bool) $i->auto_renew,
            'expires_at' => $i->expires_at?->toIso8601String(),
            'expired' => $i->isExpired(),
        ];
    }

    public static function task(InstanceTask $t): array
    {
        return [
            'id' => $t->id,
            'type' => $t->type,
            'status' => $t->status,
            'created_at' => $t->created_at?->toIso8601String(),
            'progress' => self::str($t->result['progress'] ?? null),
        ];
    }

    public static function sku(Sku $s): array
    {
        return [
            'id' => $s->id,
            'type' => $s->type,
            'name' => $s->name,
            'model' => $s->config_model,
            'android_version' => $s->type === Sku::TYPE_CLOUD_PHONE ? $s->android_version : null,
            'duration_label' => $s->duration_label,
            'duration_minutes' => (int) $s->duration_minutes,
            'price' => (float) $s->price,
            'country_code' => $s->default_country_code,
            'country_name' => $s->default_country_code ? VmosRegionCatalog::nameFor($s->default_country_code) : null,
            'sold_out' => (bool) $s->sell_out,
            'tier' => $s->type === Sku::TYPE_CLOUD_PHONE ? $s->deviceTier() : null,
        ];
    }

    public static function payment(?CryptoPayment $p): ?array
    {
        if (! $p) {
            return null;
        }

        return [
            'id' => $p->id,
            'currency' => $p->currency,
            'network' => $p->network,
            'address' => $p->pay_to_address,
            'amount_crypto' => (float) $p->amount_crypto,
            'amount_usd' => (float) $p->amount_usd,
            'status' => $p->status,
            'tx_hash' => $p->tx_hash,
            'expires_at' => $p->expires_at?->toIso8601String(),
        ];
    }

    public static function order(Order $o, bool $withDetails = false): array
    {
        $data = [
            'id' => $o->id,
            'reference' => $o->reference,
            'product' => $o->sku?->name,
            'product_type' => $o->sku?->type,
            'quantity' => (int) $o->quantity,
            'total' => (float) $o->total_price,
            'status' => $o->status,
            'error' => $o->status === Order::STATUS_FAILED ? 'This order couldn\'t be completed — please contact support.' : null,
            'proxy_status' => $o->proxy_status,
            'proxy_error' => $o->proxy_error,
            'created_at' => $o->created_at?->toIso8601String(),
            'paid_at' => $o->paid_at?->toIso8601String(),
        ];

        if ($withDetails) {
            $data['payment'] = self::payment($o->payments->first());
            $data['devices'] = $o->cloudInstances->map(fn ($i) => self::device($i))->values();
            $data['email_accounts'] = $o->emailAccounts->map(fn ($a) => self::emailAccount($a))->values();
            $data['phone_numbers'] = $o->phoneNumbers->map(fn ($n) => self::phoneNumber($n))->values();
            $data['cloud_numbers'] = $o->cloudNumbers->map(fn ($n) => self::cloudNumber($n))->values();
            $data['proxies'] = $o->customerProxies->map(fn ($p) => self::proxy($p))->values();
        }

        return $data;
    }

    public static function emailAccount(EmailAccount $a): array
    {
        return [
            'id' => $a->id,
            'product' => $a->sku?->name,
            'email' => $a->email,
            'password' => $a->password,
            'latest_code' => $a->latest_code,
            'code_fetched_at' => $a->code_fetched_at?->toIso8601String(),
            'status' => $a->status,
            'delivered_at' => $a->delivered_at?->toIso8601String(),
        ];
    }

    public static function phoneNumber(PhoneNumber $n): array
    {
        return [
            'id' => $n->id,
            'product' => $n->sku?->name,
            'phone_number' => $n->phone_number,
            'country_code' => $n->country_code,
            'latest_code' => $n->latest_code,
            'code_fetched_at' => $n->code_fetched_at?->toIso8601String(),
            'status' => $n->status,
        ];
    }

    public static function cloudNumber(CloudNumber $n): array
    {
        return [
            'id' => $n->id,
            'product' => $n->sku?->name,
            'number' => $n->number,
            'country_code' => $n->country_code,
            'country_name' => $n->country_code ? VmosRegionCatalog::nameFor($n->country_code) : null,
            'purchase_status' => self::str($n->purchase_status),
            'ready' => filled($n->vmos_number_id),
            'auto_renew' => (bool) $n->auto_renew,
            'status' => self::str($n->vmos_status),
            'expires_at' => $n->expire_time ? (string) $n->expire_time : null,
            'bound_pad_code' => $n->bound_pad_code,
            'bind_state' => self::str($n->bind_state),
            'sms' => collect($n->raw_payload['sms'] ?? [])->map(fn ($m) => [
                'from' => self::str($m['sender'] ?? $m['from'] ?? $m['phone'] ?? null),
                'text' => self::str($m['content'] ?? $m['text'] ?? $m['message'] ?? null),
                'at' => self::str($m['receiveTime'] ?? $m['time'] ?? $m['createTime'] ?? null),
            ])->values(),
        ];
    }

    public static function proxy(CustomerProxy $p): array
    {
        return [
            'id' => $p->id,
            'source' => $p->source,
            'label' => $p->label,
            'remarks' => $p->remarks,
            'host' => $p->host,
            'port' => $p->port ? (int) $p->port : null,
            'account' => $p->account,
            'has_password' => filled($p->password),
            'proxy_name' => $p->proxy_name,
            'proxy_type' => $p->proxy_type,
            'country_code' => $p->country_code,
            'country_name' => $p->country_code ? VmosRegionCatalog::nameFor($p->country_code) : null,
            'purchase_status' => self::str($p->purchase_status),
            'delivered' => $p->isDelivered(),
            'attached_pad_code' => $p->attached_pad_code,
            'product' => $p->sku?->name,
            'expires_at' => self::str($p->raw_payload['expireTime'] ?? $p->raw_payload['expirationTime'] ?? null),
        ];
    }

    /** Provider values can arrive as numbers or strings; the app always gets a string. */
    public static function str(mixed $value): ?string
    {
        return $value === null || is_array($value) ? null : (string) $value;
    }

    public static function deposit(WalletDeposit $d): array
    {
        return [
            'id' => $d->id,
            'currency' => $d->currency,
            'network' => $d->network,
            'address' => $d->pay_to_address,
            'amount_crypto' => (float) $d->amount_crypto,
            'amount_usd' => (float) $d->amount_usd,
            'status' => $d->status,
            'tx_hash' => $d->tx_hash,
            'expires_at' => $d->expires_at?->toIso8601String(),
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }

    public static function transaction(WalletTransaction $t): array
    {
        return [
            'id' => $t->id,
            'type' => $t->type,
            'amount' => (float) $t->amount,
            'balance_after' => (float) $t->balance_after,
            'description' => $t->description,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }
}
