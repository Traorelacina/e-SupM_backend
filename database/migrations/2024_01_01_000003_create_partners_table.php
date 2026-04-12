<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone');
            $table->text('address');
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->json('proof_images')->nullable();
            $table->enum('type', ['supplier','delivery','advertiser','producer'])->default('supplier');
            $table->enum('status', ['pending','approved','rejected','suspended'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('show_on_homepage')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('client_name')->nullable();
            $table->string('image');
            $table->string('link')->nullable();
            $table->enum('position', ['large_center','left','right','banner_top','banner_bottom'])->default('large_center');
            $table->enum('page', ['home','catalogue','promo','game','all'])->default('home');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_flashing')->default(false); // clignotant
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('slide_count')->default(3);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('advertisements');
        Schema::dropIfExists('partners');
    }
};
