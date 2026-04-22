<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charity_donations', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('loyalty_points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('charity_donations', function (Blueprint $table) {
            $table->dropColumn('admin_note');
        });
    }
};