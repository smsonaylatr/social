<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('growth_strategy', 32)->default('balanced')->after('schedule_type');
            $table->boolean('anti_spam_shield')->default(true)->after('growth_strategy');
            $table->boolean('enable_smart_jitter')->default(true)->after('anti_spam_shield');
            $table->boolean('enable_staggered_dispatch')->default(true)->after('enable_smart_jitter');
        });

        Schema::table('scheduled_post_targets', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('platform_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn([
                'growth_strategy',
                'anti_spam_shield',
                'enable_smart_jitter',
                'enable_staggered_dispatch',
            ]);
        });

        Schema::table('scheduled_post_targets', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
