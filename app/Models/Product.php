<?php

namespace App\Models;

use App\Models\Concerns\HasOffers;

use App\Enums\StorageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasOffers, SoftDeletes;

    protected $guarded = [];

    // effective_price, is_on_offer and discount_percent accessors come in step 4 (spec 6.9).
    protected function casts(): array
    {
        return [
            'allergens' => 'array',
            'storage_type' => StorageType::class,
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function pairedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_pairs', 'product_id', 'paired_product_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }


    /** Inverse of pairedProducts(): products that list this one as a pair. Filament's Attach looks for it. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_pairs', 'paired_product_id', 'product_id')
            ->withPivot('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
