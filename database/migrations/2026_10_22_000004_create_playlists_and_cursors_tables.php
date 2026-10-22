<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('loop_mode')->default('sequential'); // sequential, shuffle, priority
            $table->boolean('is_circular')->default(true); // Loop back to start
            $table->unsignedInteger('cooldown_days')->default(14); // Min days before re-posting same video
            $table->boolean('pause_when_exhausted')->default(false);
            $table->boolean('prioritize_new_videos')->default(true);
            $table->string('status')->default('active'); // active, paused, waiting_content, error
            $table->json('platform_rules')->nullable(); // custom cooldown per platform
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('priority_score')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['playlist_id', 'video_id']);
        });

        Schema::create('playlist_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_index')->default(0);
            $table->foreignId('current_video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->unsignedInteger('cycle_count')->default(0);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamps();

            $table->unique('playlist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_cursors');
        Schema::dropIfExists('playlist_items');
        Schema::dropIfExists('playlists');
    }
};
