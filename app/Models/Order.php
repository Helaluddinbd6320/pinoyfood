<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'free_delivery_applied' => 'boolean',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_online_total' => 'decimal:2',
            'amount_due_on_delivery' => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'collected_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}