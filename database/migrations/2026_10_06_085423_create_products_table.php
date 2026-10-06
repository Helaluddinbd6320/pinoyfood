<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('name_tl', 150)->nullable();
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->json('allergens')->nullable();
            $table->string('storage_type', 20)->default('ambient');   // frozen, chilled, ambient
            $table->unsignedSmallInteger('shelf_life_days')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->unsignedSmallInteger('low_stock_threshold')->default(5);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};