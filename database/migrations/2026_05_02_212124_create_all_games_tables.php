<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. game_defis (sans clé étrangère circulaire)
        if (!Schema::hasTable('game_defis')) {
            Schema::create('game_defis', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('challenge_text');
                $table->string('challenge_video_url')->nullable();
                $table->string('image')->nullable();
                $table->enum('status', ['draft', 'active', 'voting', 'closed'])->default('draft');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('voting_ends_at')->nullable();
                $table->unsignedBigInteger('winner_participant_id')->nullable();
                $table->text('prize_description')->nullable();
                $table->string('prize_image')->nullable();
                $table->integer('loyalty_points_prize')->default(0);
                $table->timestamps();
            });
        }

        // 2. game_defi_participants
        if (!Schema::hasTable('game_defi_participants')) {
            Schema::create('game_defi_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('game_defi_id');
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('submission_text')->nullable();
                $table->string('submission_image')->nullable();
                $table->string('submission_video_url')->nullable();
                $table->unsignedInteger('votes_count')->default(0);
                $table->boolean('is_selected')->default(false);
                $table->boolean('is_winner')->default(false);
                $table->boolean('prize_claimed')->default(false);
                $table->timestamp('earned_at')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();
                $table->unique(['game_defi_id', 'user_id']);
            });
        }

        // 3. game_defi_votes
        if (!Schema::hasTable('game_defi_votes')) {
            Schema::create('game_defi_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('game_defi_id')->constrained()->cascadeOnDelete();
                $table->foreignId('participant_id')->constrained('game_defi_participants')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['game_defi_id', 'user_id']);
            });
        }

        // 4. scratch_cards
        if (!Schema::hasTable('scratch_cards')) {
            Schema::create('scratch_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('month_year', 7);
                $table->enum('trigger_type', ['purchase', 'charity', 'manual'])->default('purchase');
                $table->decimal('trigger_amount', 10, 0)->default(0);
                $table->boolean('is_scratched')->default(false);
                $table->timestamp('scratched_at')->nullable();
                $table->enum('prize_type', ['product', 'points', 'voucher', 'delivery', 'travel', 'hotel', 'empty'])->nullable();
                $table->string('prize_label')->nullable();
                $table->decimal('prize_value', 10, 2)->default(0);
                $table->text('prize_description')->nullable();
                $table->string('prize_image')->nullable();
                $table->boolean('prize_claimed')->default(false);
                $table->timestamp('prize_claimed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'month_year', 'trigger_type']);
            });
        }

        // 5. wheel_configs
        if (!Schema::hasTable('wheel_configs')) {
            Schema::create('wheel_configs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('wheel_type', ['wholesale', 'standard']);
                $table->decimal('min_purchase_amount', 10, 0)->default(0);
                $table->tinyInteger('spins_per_month')->default(1);
                $table->boolean('is_active')->default(true);
                $table->json('prizes');
                $table->timestamps();
            });
        }

        // 6. wheel_spins
        if (!Schema::hasTable('wheel_spins')) {
            Schema::create('wheel_spins', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('wheel_config_id')->constrained()->cascadeOnDelete();
                $table->string('month_year', 7);
                $table->tinyInteger('spin_number')->default(1);
                $table->string('prize_label');
                $table->string('prize_type');
                $table->decimal('prize_value', 10, 2)->default(0);
                $table->boolean('prize_claimed')->default(false);
                $table->timestamp('prize_claimed_at')->nullable();
                $table->enum('triggered_by', ['purchase_threshold', 'manual_admin'])->default('purchase_threshold');
                $table->foreignId('trigger_order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id', 'wheel_config_id', 'month_year']);
            });
        }

        // 7. quiz_sessions
        if (!Schema::hasTable('quiz_sessions')) {
            Schema::create('quiz_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('theme');
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->integer('time_limit_seconds')->default(60);
                $table->text('prize_description')->nullable();
                $table->string('prize_image')->nullable();
                $table->integer('loyalty_points_prize')->default(0);
                $table->tinyInteger('min_score_to_win')->default(100);
                $table->integer('retry_delay_hours')->default(72);
                $table->timestamps();
            });
        }

        // 8. quiz_questions
        if (!Schema::hasTable('quiz_questions')) {
            Schema::create('quiz_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
                $table->text('question_text');
                $table->string('question_image')->nullable();
                $table->enum('type', ['multiple_choice', 'true_false', 'text_input'])->default('multiple_choice');
                $table->integer('points')->default(10);
                $table->integer('order')->default(0);
                $table->text('explanation')->nullable();
                $table->timestamps();
            });
        }

        // 9. quiz_options
        if (!Schema::hasTable('quiz_options')) {
            Schema::create('quiz_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
                $table->string('option_text');
                $table->string('option_image')->nullable();
                $table->boolean('is_correct')->default(false);
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }

        // 10. quiz_participations
        if (!Schema::hasTable('quiz_participations')) {
            Schema::create('quiz_participations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('score', 5, 2)->default(0);
                $table->integer('total_points')->default(0);
                $table->json('answers')->nullable();
                $table->integer('time_taken_seconds')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->boolean('won')->default(false);
                $table->text('prize_description')->nullable();
                $table->integer('loyalty_points_won')->default(0);
                $table->timestamp('next_retry_at')->nullable();
                $table->timestamps();
                $table->index(['quiz_session_id', 'user_id']);
            });
        }

        // 11. battle_contests
        if (!Schema::hasTable('battle_contests')) {
            Schema::create('battle_contests', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->enum('type', ['promo', 'product', 'team']);
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedBigInteger('winner_candidate_id')->nullable();
                $table->text('prize_description')->nullable();
                $table->integer('loyalty_points_prize')->default(0);
                $table->timestamps();
            });
        }

        // 12. battle_candidates
        if (!Schema::hasTable('battle_candidates')) {
            Schema::create('battle_candidates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('battle_contest_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('image')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('votes_count')->default(0);
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }

        // 13. battle_votes
        if (!Schema::hasTable('battle_votes')) {
            Schema::create('battle_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('battle_contest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('candidate_id')->constrained('battle_candidates')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['battle_contest_id', 'user_id']);
            });
        }

        // 14. juste_prix (important : singulier)
        if (!Schema::hasTable('juste_prix')) {
            Schema::create('juste_prix', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->text('prize_description')->nullable();
                $table->string('prize_image')->nullable();
                $table->integer('loyalty_points_prize')->default(0);
                $table->tinyInteger('tolerance_percent')->default(5);
                $table->timestamps();
            });
        }

        // 15. juste_prix_participations (contrainte corrigée)
        if (!Schema::hasTable('juste_prix_participations')) {
            Schema::create('juste_prix_participations', function (Blueprint $table) {
                $table->id();
                // ICI : on précise 'juste_prix' au lieu de laisser le pluriel automatique
                $table->foreignId('juste_prix_id')->constrained('juste_prix')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->decimal('correct_price', 10, 0);
                $table->decimal('guessed_price', 10, 0)->nullable();
                $table->integer('time_limit_seconds')->default(30);
                $table->integer('time_taken_seconds')->nullable();
                $table->boolean('is_correct')->default(false);
                $table->boolean('is_close')->default(false);
                $table->boolean('won')->default(false);
                $table->text('prize_description')->nullable();
                $table->integer('loyalty_points_won')->default(0);
                $table->timestamp('next_allowed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'juste_prix_id']);
            });
        }

        // ---- Ajout des clés étrangères circulaires ----
        if (Schema::hasTable('game_defi_participants') && Schema::hasTable('game_defis')) {
            Schema::table('game_defi_participants', function (Blueprint $table) {
                $table->foreign('game_defi_id')->references('id')->on('game_defis')->cascadeOnDelete();
            });
        }
        if (Schema::hasTable('game_defis') && Schema::hasTable('game_defi_participants')) {
            Schema::table('game_defis', function (Blueprint $table) {
                $table->foreign('winner_participant_id')->references('id')->on('game_defi_participants')->nullOnDelete();
            });
        }
        if (Schema::hasTable('battle_contests') && Schema::hasTable('battle_candidates')) {
            Schema::table('battle_contests', function (Blueprint $table) {
                $table->foreign('winner_candidate_id')->references('id')->on('battle_candidates')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Supprimer les clés étrangères ajoutées
        if (Schema::hasTable('game_defi_participants') && Schema::hasColumn('game_defi_participants', 'game_defi_id')) {
            Schema::table('game_defi_participants', function (Blueprint $table) {
                $table->dropForeign(['game_defi_id']);
            });
        }
        if (Schema::hasTable('game_defis') && Schema::hasColumn('game_defis', 'winner_participant_id')) {
            Schema::table('game_defis', function (Blueprint $table) {
                $table->dropForeign(['winner_participant_id']);
            });
        }
        if (Schema::hasTable('battle_contests') && Schema::hasColumn('battle_contests', 'winner_candidate_id')) {
            Schema::table('battle_contests', function (Blueprint $table) {
                $table->dropForeign(['winner_candidate_id']);
            });
        }

        $tables = [
            'juste_prix_participations',
            'juste_prix',
            'battle_votes',
            'battle_candidates',
            'battle_contests',
            'quiz_participations',
            'quiz_options',
            'quiz_questions',
            'quiz_sessions',
            'wheel_spins',
            'wheel_configs',
            'scratch_cards',
            'game_defi_votes',
            'game_defi_participants',
            'game_defis',
        ];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
            }
        }
    }
};