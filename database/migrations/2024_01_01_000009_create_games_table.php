<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('video_url')->nullable();
            $table->enum('type', ['defi','carte_gratter','roue','quiz','juste_prix','battle','calendrier']);
            $table->enum('status', ['upcoming','active','closed','draft'])->default('draft');
            $table->boolean('is_open_to_all')->default(true);
            $table->boolean('requires_registration')->default(false);
            $table->boolean('requires_purchase')->default(false);
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->enum('purchase_type', ['all','wholesale','both'])->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('auto_activate_day')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('participation_cooldown_days')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->boolean('has_countdown')->default(false);
            $table->unsignedInteger('time_limit_seconds')->nullable();
            $table->text('prizes')->nullable();
            $table->unsignedInteger('loyalty_points_prize')->default(0);
            $table->timestamps();
        });

        Schema::create('game_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->integer('score')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->string('prize')->nullable();
            $table->boolean('prize_claimed')->default(false);
            $table->unsignedInteger('loyalty_points_won')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('participated_at');
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->json('options');
            $table->string('correct_answer');
            $table->string('theme')->nullable();
            $table->unsignedInteger('points')->default(10);
            $table->unsignedInteger('time_limit_seconds')->default(30);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('wheel_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('type')->nullable();
            $table->string('value')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('probability')->default(10);
            $table->timestamps();
        });

        Schema::create('battle_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('image')->nullable();
            $table->enum('battle_type', ['promo','rayon','team'])->default('promo');
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();
        });

        Schema::create('battle_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('battle_candidate_id')->constrained()->cascadeOnDelete();
            $table->string('battle_type'); // ← Colonne ajoutée ici
            $table->timestamps();
            $table->unique(['game_id', 'user_id', 'battle_type'], 'battle_votes_unique');
        });
    }
    
    public function down(): void {
        Schema::dropIfExists('battle_votes');
        Schema::dropIfExists('battle_candidates');
        Schema::dropIfExists('wheel_prizes');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('game_participants');
        Schema::dropIfExists('games');
    }
};