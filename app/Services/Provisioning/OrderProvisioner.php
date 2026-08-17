<?php

namespace App\Services\Provisioning;

use App\Models\Order;
use App\Models\Sku;

/**
 * Dispatches a paid Order to the right provisioner for its Sku type, so the
 * checkout/payment/cron pipeline stays shared between cloud phones and email
 * accounts instead of forking.
 */
class OrderProvisioner
{
    public function __construct(
        protected CloudPhoneProvisioner $cloudPhones,
        protected EmailAccountProvisioner $emailAccounts,
    ) {
    }

    public function provision(Order $order): void
    {
        $order->loadMissing('sku');

        match ($order->sku?->type) {
            Sku::TYPE_EMAIL_ACCOUNT => $this->emailAccounts->provision($order),
            default => $this->cloudPhones->provision($order),
        };
    }
}
