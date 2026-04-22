<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('tagline')->nullable();          // Ex: "La box complète pour votre famille"
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);               // Prix affiché
            $table->decimal('compare_price', 10, 2)->nullable(); // Prix barré
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly'])->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('max_subscribers')->nullable(); // null = illimité
            $table->integer('subscribers_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->string('badge_label')->nullable();      // Ex: "Populaire", "Nouveau"
            $table->string('badge_color')->nullable();      // Ex: "#f59e0b"
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('food_box_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_box_id')->constrained('food_boxes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['food_box_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_box_items');
        Schema::dropIfExists('food_boxes');
    }
};