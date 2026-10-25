<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('schedule_type')->default('single')->after('name'); // 'single', 'custom_times', 'interval'
            $table->json('posting_times')->nullable()->after('time_of_day');
            $table->unsignedInteger('interval_hours')->nullable()->after('posting_times');
            $table->time('start_time')->nullable()->after('interval_hours');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_type',
                'posting_times',
                'interval_hours',
                'start_time',
                'end_time',
            ]);
        });
    }
};
