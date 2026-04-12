<?php
// 2024_01_01_000002_create_addresses_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Domicile'); // Domicile, Bureau, etc.
            $table->string('recipient_name');
            $table->string('phone');
            $table->text('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('district')->nullable(); // Quartier
            $table->string('country')->default('CI');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('addresses'); }
};
