<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('status', [
                'pending','confirmed','paid','preparing','ready',
                'dispatched','delivered','cancelled','refunded'
            ])->default('pending');

            $table->enum('delivery_type', ['home','click_collect','locker'])->default('home');
            $table->string('pickup_store')->nullable(); // pour click & collect
            $table->string('locker_id')->nullable();

            $table->enum('payment_method', ['card','mobile_money','cinetpay','paydunya','cash'])->nullable();
            $table->enum('payment_status', ['pending','paid','failed','refunded'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->string('transaction_id')->nullable();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('loyalty_discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('coupon_code')->nullable();

            $table->unsignedInteger('loyalty_points_earned')->default(0);
            $table->unsignedInteger('loyalty_points_used')->default(0);

            $table->text('notes')->nullable();
            $table->string('tracking_code')->nullable();

            // Subscription order flag
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_subscription_order')->default(false);
            $table->boolean('is_priority')->default(false); // abonnement = prioritaire

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('order_number');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->string('product_image')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('total', 10, 2);
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->enum('preparation_status', ['pending','preparing','ready','substituted','unavailable'])->default('pending');
            $table->foreignId('substitute_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
