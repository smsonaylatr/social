<?php

use App\Models\Schedule;
use App\Models\ScheduledPostTarget;
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
        if (Schema::hasTable('schedules') && ! Schema::hasColumn('schedules', 'last_video_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->foreignId('last_video_id')
                    ->nullable()
                    ->after('playlist_id')
                    ->constrained('videos')
                    ->nullOnDelete();
            });

            // Backfill initial last_video_id for existing platform schedules based on their target social accounts
            try {
                foreach (Schedule::all() as $schedule) {
                    $targetAccountIds = $schedule->target_social_account_ids ?: [];
                    if (! empty($targetAccountIds)) {
                        $lastVideoId = ScheduledPostTarget::join('scheduled_posts', 'scheduled_post_targets.scheduled_post_id', '=', 'scheduled_posts.id')
                            ->whereIn('scheduled_post_targets.social_account_id', $targetAccountIds)
                            ->whereNotNull('scheduled_posts.video_id')
                            ->orderBy('scheduled_post_targets.id', 'desc')
                            ->value('scheduled_posts.video_id');

                        if ($lastVideoId) {
                            $schedule->updateQuietly(['last_video_id' => $lastVideoId]);
                        }
                    }
                }
            } catch (\Throwable) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('schedules') && Schema::hasColumn('schedules', 'last_video_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropForeign(['last_video_id']);
                $table->dropColumn('last_video_id');
            });
        }
    }
};
