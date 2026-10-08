<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/** Handles STOP (spec 6.7): set opted_out_at and log it in consent_logs. */
class WhatsappConsentService
{
    public function recordStop(Customer $customer, string $source = 'admin'): Customer
    {
        if (! $customer->whatsapp_opt_in) {
            return $customer;
        }

        DB::transaction(function () use ($customer, $source): void {
            $now = now();

            $customer->forceFill(['whatsapp_opt_in' => false, 'opted_out_at' => $now])->save();

            DB::table('consent_logs')->insert([
                'customer_id' => $customer->id,
                'action' => 'opt_out',
                'source' => $source,
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => $now,
            ]);

            DB::table('whatsapp_messages')->insert([
                'customer_id' => $customer->id,
                'direction' => 'in',
                'kind' => 'stop',
                'body' => 'STOP',
                'status' => 'delivered',
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $customer->refresh();
    }
}