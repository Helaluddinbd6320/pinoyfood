<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reward_amount' => 'decimal:2'];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id')->withTrashed();
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id')->withTrashed();
    }

    public function firstOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'first_order_id');
    }
}