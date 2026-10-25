<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedInteger('max_posts_per_day')->nullable()->default(null)->change();
        });

        // Mevcut kayıtlar varsa limiti sınırsız (null) yap
        DB::table('schedules')->update(['max_posts_per_day' => null]);
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedInteger('max_posts_per_day')->default(3)->change();
        });
    }
};
