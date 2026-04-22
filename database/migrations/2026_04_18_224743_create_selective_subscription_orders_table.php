<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('selective_subscription_id')
                ->nullable()
                ->after('user_id')
                ->constrained('selective_subscriptions')
                ->nullOnDelete();
            
            $table->index('selective_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['selective_subscription_id']);
            $table->dropColumn('selective_subscription_id');
        });
    }
};