<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Storefront customer. Authenticatable through the "customer" guard only
 * (Google sign-in, no password). Staff use App\Models\User.
 */
class Customer extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'default_lat' => 'decimal:7',
            'default_lng' => 'decimal:7',
            'whatsapp_opt_in' => 'boolean',
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'requires_prepay' => 'boolean',
            'total_spent' => 'decimal:2',
            'last_order_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_by_customer_id');
    }

    public function referredCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'referred_by_customer_id');
    }

    public function consentLogs(): HasMany
    {
        return $this->hasMany(ConsentLog::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}