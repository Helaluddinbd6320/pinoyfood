<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->nullable();
            $table->string('email', 190)->nullable()->unique();
            $table->string('google_id', 64)->nullable()->unique();
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone_raw', 40)->nullable();
            $table->string('phone_e164', 20)->nullable()->unique();
            $table->string('phone_search', 12)->nullable()->index();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('address_notes', 255)->nullable();
            $table->string('default_pin_url', 500)->nullable();
            $table->decimal('default_lat', 10, 7)->nullable();
            $table->decimal('default_lng', 10, 7)->nullable();
            $table->boolean('whatsapp_opt_in')->default(false);
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->string('opt_in_source', 30)->nullable();   // checkout, community_group, admin, chat
            $table->string('referral_code', 12)->unique();
            $table->foreignId('referred_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->boolean('requires_prepay')->default(false);
            $table->unsignedSmallInteger('no_answer_count')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};