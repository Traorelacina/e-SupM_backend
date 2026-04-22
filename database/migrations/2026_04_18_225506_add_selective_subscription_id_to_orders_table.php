<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la colonne n'existe pas déjà
        if (!Schema::hasColumn('orders', 'selective_subscription_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('selective_subscription_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('selective_subscriptions')
                    ->nullOnDelete();
                
                $table->index('selective_subscription_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'selective_subscription_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['selective_subscription_id']);
                $table->dropColumn('selective_subscription_id');
            });
        }
    }
};