<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 30);                        // delivery_fee_prepay, deposit, full
            $table->string('method', 20);                      // stc_pay, bank_transfer, other
            $table->decimal('amount', 10, 2);
            $table->string('status', 25)->default('awaiting_proof');
            $table->string('proof_path', 255)->nullable();     // private disk only
            $table->timestamp('proof_uploaded_at')->nullable();
            $table->string('proof_hash', 64)->nullable()->index();
            $table->string('customer_reference', 100)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_ref', 100)->nullable()->unique();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};