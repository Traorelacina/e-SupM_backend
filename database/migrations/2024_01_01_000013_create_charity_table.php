<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('charity_donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['voucher','product'])->default('voucher');
            $table->decimal('amount', 10, 2)->nullable(); // pour vouchers
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->enum('payment_method', ['mobile_money','virement','card'])->nullable();
            $table->string('payment_reference')->nullable();
            $table->enum('status', ['pending','confirmed','distributed'])->default('pending');
            $table->unsignedInteger('loyalty_points_earned')->default(0);
            $table->boolean('scratch_card_unlocked')->default(false);
            $table->timestamps();
        });

        Schema::create('charity_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('donation_id')->constrained('charity_donations')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_used')->default(false);
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('charity_vouchers');
        Schema::dropIfExists('charity_donations');
    }
};
