<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('referred_customer_id')->unique()->constrained('customers')->restrictOnDelete();
            $table->foreignId('first_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('reward_amount', 8, 2)->default(0);
            $table->string('status', 20)->default('pending');   // pending, rewarded
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};