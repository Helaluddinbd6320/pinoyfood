<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('paired_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->unique(['product_id', 'paired_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pairs');
    }
};