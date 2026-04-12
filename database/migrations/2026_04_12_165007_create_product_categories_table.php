<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('color', 20)->default('#FBBF24');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index('sort_order');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_category_id')
                  ->nullable()
                  ->after('category_id')
                  ->constrained('product_categories')
                  ->nullOnDelete();

            $table->index('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['product_category_id']);
            $table->dropIndex(['product_category_id']);
            $table->dropColumn('product_category_id');
        });

        Schema::dropIfExists('product_categories');
    }
};