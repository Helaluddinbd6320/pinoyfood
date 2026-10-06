<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_co_purchases', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('other_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('times_together')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->primary(['product_id', 'other_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_co_purchases');
    }
};