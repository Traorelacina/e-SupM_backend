<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_price', 12, 2)->nullable(); // prix barré
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('weight', 8, 3)->nullable(); // kg
            $table->string('unit')->default('unité'); // kg, g, L, ml, unité
            $table->string('brand')->nullable();
            $table->string('origin')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('track_stock')->default(true);

            // Labels / étiquettes
            $table->boolean('is_bio')->default(false);
            $table->boolean('is_local')->default(false);
            $table->boolean('is_eco')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_gluten_free')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_bestseller')->default(false);

            // Admin labels
            $table->enum('admin_label', ['none','stock_limite','promo','stock_epuise','offre_limitee','vote_rayon'])->default('none');
            $table->unsignedSmallInteger('admin_label_discount')->nullable(); // 10, 20, 50, 70

            // Status
            $table->boolean('is_active')->default(true);
            $table->date('expiry_date')->nullable(); // pour déstockage
            $table->enum('expiry_alert', ['none','red','orange','yellow'])->default('none');

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Stats
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
            $table->index(['is_featured', 'is_active']);
            $table->index(['is_new', 'is_active']);
            $table->fullText(['name', 'description', 'brand']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_size_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('size');
            $table->string('color')->nullable();
            $table->decimal('extra_price', 10, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
        });
    }
    public function down(): void {
        Schema::dropIfExists('product_size_options');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
