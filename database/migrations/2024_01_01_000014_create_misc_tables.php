<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Reviews
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('loyalty_points_earned')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'product_id', 'order_id']);
        });

        // Recipes
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->json('ingredients'); // [{name, quantity, unit, product_id?}]
            $table->json('steps'); // [{order, instruction, image?}]
            $table->unsignedInteger('prep_time_minutes')->nullable();
            $table->unsignedInteger('cook_time_minutes')->nullable();
            $table->unsignedInteger('servings')->nullable();
            $table->string('difficulty')->nullable(); // facile, moyen, difficile
            $table->string('category')->nullable(); // nutrition, astuce, etc.
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });

        // Wishlists
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        // Suggestion box
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->nullable();
            $table->text('message');
            $table->enum('status', ['new','reviewed','implemented','rejected'])->default('new');
            $table->text('admin_response')->nullable();
            $table->timestamps();
        });

        // Newsletter subscriptions
        Schema::create('newsletter_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('token')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        // Delegate shopping
        Schema::create('delegate_shopping_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('list_text')->nullable();
            $table->string('list_image')->nullable();
            $table->string('list_audio')->nullable();
            $table->enum('delivery_type', ['home','store_koumassi'])->default('home');
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->enum('status', ['received','processing','ready','delivered','cancelled'])->default('received');
            $table->decimal('estimated_amount', 10, 2)->nullable();
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->boolean('partial_payment_made')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Push notification subscriptions
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('endpoint')->unique();
            $table->text('public_key')->nullable();
            $table->text('auth_token')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('delegate_shopping_requests');
        Schema::dropIfExists('newsletter_subscriptions');
        Schema::dropIfExists('suggestions');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('reviews');
    }
};
