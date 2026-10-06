<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name', 150);              // snapshot
            $table->decimal('list_price', 10, 2);             // normal price snapshot
            $table->decimal('unit_price', 10, 2);             // price charged, incl. any offer
            $table->decimal('unit_cost', 10, 2)->nullable();  // snapshot
            $table->unsignedSmallInteger('qty');
            $table->decimal('line_total', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};