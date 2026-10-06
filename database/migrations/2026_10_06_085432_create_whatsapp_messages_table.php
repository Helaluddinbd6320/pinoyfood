<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained('broadcasts')->nullOnDelete();
            $table->string('direction', 5);                    // in, out
            $table->string('kind', 20);                        // offer, order_status, reply, stop
            $table->text('body');
            $table->string('provider_message_id', 100)->nullable();
            $table->string('status', 20);                      // queued, sent, delivered, failed
            $table->string('error', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};