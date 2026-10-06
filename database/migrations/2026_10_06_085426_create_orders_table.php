<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 20)->unique();   // example PF-1049
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('source', 20);                // website, whatsapp, call, admin
            $table->string('status', 20)->default('new');

            // Snapshots
            $table->string('customer_name', 120);
            $table->string('phone_raw', 40)->nullable();
            $table->string('phone_e164', 20)->nullable();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('address_notes', 255)->nullable();
            $table->string('location_pin_url', 500)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->date('delivery_date')->nullable();
            $table->string('slot_label', 40)->nullable();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->boolean('free_delivery_applied')->default(false);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('paid_online_total', 10, 2)->default(0);
            $table->decimal('amount_due_on_delivery', 10, 2);

            $table->string('delivery_payment_method', 20)->nullable();   // cash, card
            $table->decimal('collected_amount', 10, 2)->nullable();
            $table->timestamp('collected_at')->nullable();

            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_notes', 255)->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['delivery_date', 'status']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};