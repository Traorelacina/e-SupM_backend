<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'assigned','picked_up','in_transit','arrived','delivered','failed'
            ])->default('assigned');
            $table->string('tracking_code')->nullable();
            $table->decimal('driver_latitude', 10, 8)->nullable();
            $table->decimal('driver_longitude', 11, 8)->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('delivery_proof_image')->nullable();
            $table->string('recipient_name')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('message');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('delivery_tracking_events');
        Schema::dropIfExists('deliveries');
    }
};
