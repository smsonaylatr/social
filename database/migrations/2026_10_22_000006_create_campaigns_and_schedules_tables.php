<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('status')->default('active'); // draft, active, paused, completed
            $table->json('target_platforms')->nullable();
            $table->json('target_social_account_ids')->nullable();
            $table->text('target_url')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->time('time_of_day')->default('12:00:00');
            $table->json('days_of_week')->nullable(); // [1,2,3,4,5,6,7]
            $table->string('cron_expression')->nullable();
            $table->json('target_social_account_ids')->nullable();
            $table->unsignedInteger('max_posts_per_day')->default(3);
            $table->boolean('is_active')->default(false); // Initial safety: false
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->string('status')->default('scheduled'); // scheduled, queued, processing, published, partially_published, failed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduled_post_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform_code')->index();
            $table->string('correlation_id', 64)->unique(); // Unique trace ID
            $table->string('idempotency_key', 64)->unique(); // Prevents duplicate execution
            $table->string('status')->default('scheduled'); // scheduled, queued, uploading, processing, published, failed, retrying, blocked_policy
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('product_url')->nullable();
            $table->string('external_post_id')->nullable(); // ID on Facebook, YouTube, etc.
            $table->text('external_post_url')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();
        });

        Schema::create('publication_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_post_target_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status'); // success, failed, rate_limited, timeout
            $table->integer('http_status_code')->nullable();
            $table->text('request_payload_summary')->nullable(); // Filtered/sanitized
            $table->text('response_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_attempts');
        Schema::dropIfExists('scheduled_post_targets');
        Schema::dropIfExists('scheduled_posts');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('campaigns');
    }
};
