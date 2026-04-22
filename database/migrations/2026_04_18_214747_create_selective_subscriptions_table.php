<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('selective_subscriptions')) {
            Schema::create('selective_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('name', 120)->default('Mon panier sélectif');
                $table->enum('frequency', ['weekly', 'biweekly', 'monthly']);
                $table->integer('delivery_day')->nullable();
                $table->integer('delivery_week_of_month')->nullable();
                $table->enum('delivery_type', ['home', 'click_collect', 'locker']);
                $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
                $table->enum('payment_method', ['auto', 'manual'])->default('auto');
                $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
                $table->decimal('discount_percent', 5, 2)->default(5);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->timestamp('next_delivery_at')->nullable();
                $table->timestamp('suspended_until')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                
                $table->index(['user_id', 'status'], 'sub_user_status');
                $table->index('next_delivery_at', 'sub_next_delivery');
            });
        }

        if (!Schema::hasTable('selective_subscription_items')) {
            Schema::create('selective_subscription_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('selective_subscription_id')
                    ->constrained('selective_subscriptions')
                    ->onDelete('cascade');
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->integer('quantity')->default(1);
                $table->decimal('price', 12, 2);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                
                $table->unique(['selective_subscription_id', 'product_id'], 'sub_prod_unique');
                $table->index(['selective_subscription_id', 'is_active'], 'sub_items_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('selective_subscription_items');
        Schema::dropIfExists('selective_subscriptions');
    }
};