<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->enum('type', ['standard','custom'])->default('custom');
            $table->string('preset_type')->nullable(); // foyer, famille, bio, entretien

            $table->enum('frequency', ['weekly','biweekly','monthly'])->default('monthly');
            $table->unsignedTinyInteger('delivery_day')->nullable(); // 1=lundi, etc.
            $table->unsignedTinyInteger('delivery_week_of_month')->nullable(); // 1=1ère semaine

            $table->enum('delivery_type', ['home','click_collect','locker'])->default('home');
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('payment_method', ['auto','manual'])->default('manual');
            $table->string('payment_token')->nullable(); // token paiement récurrent

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(5); // 5% abonné
            $table->decimal('total', 12, 2)->default(0);

            $table->enum('status', ['active','suspended','cancelled','pending'])->default('pending');
            $table->timestamp('next_delivery_at')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->unsignedInteger('total_orders_generated')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->unique(['subscription_id', 'product_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }
};
