<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedules') && ! Schema::hasColumn('schedules', 'post_type')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->string('post_type', 32)->default('video')->after('schedule_type');
            });
        }

        if (Schema::hasTable('scheduled_posts') && ! Schema::hasColumn('scheduled_posts', 'post_type')) {
            Schema::table('scheduled_posts', function (Blueprint $table) {
                $table->string('post_type', 32)->default('video')->after('video_id');
            });
        }

        if (Schema::hasTable('scheduled_post_targets') && ! Schema::hasColumn('scheduled_post_targets', 'post_type')) {
            Schema::table('scheduled_post_targets', function (Blueprint $table) {
                $table->string('post_type', 32)->default('video')->after('platform_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('schedules') && Schema::hasColumn('schedules', 'post_type')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn('post_type');
            });
        }

        if (Schema::hasTable('scheduled_posts') && Schema::hasColumn('scheduled_posts', 'post_type')) {
            Schema::table('scheduled_posts', function (Blueprint $table) {
                $table->dropColumn('post_type');
            });
        }

        if (Schema::hasTable('scheduled_post_targets') && Schema::hasColumn('scheduled_post_targets', 'post_type')) {
            Schema::table('scheduled_post_targets', function (Blueprint $table) {
                $table->dropColumn('post_type');
            });
        }
    }
};
