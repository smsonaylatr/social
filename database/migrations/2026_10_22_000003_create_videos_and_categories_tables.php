<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('video_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('disk')->default('public');
            $table->string('file_hash', 64)->index(); // SHA-256 for duplicate detection
            $table->unsignedBigInteger('file_size'); // bytes
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->string('aspect_ratio', 16)->default('9:16');
            $table->decimal('fps', 6, 2)->default(30.00);
            $table->string('mime_type')->default('video/mp4');
            $table->string('thumbnail_path')->nullable();
            $table->string('status')->default('ready'); // pending, processing, ready, failed, archived
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_reusable')->default(true);
            $table->json('restricted_platforms')->nullable(); // Platforms blocked from publishing this video
            $table->text('product_url')->nullable();
            $table->text('custom_caption')->nullable();
            $table->json('custom_hashtags')->nullable();
            $table->json('technical_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('video_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('variant_type'); // reels_9_16, feed_1_1, landscape_16_9
            $table->string('storage_path');
            $table->string('disk')->default('public');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('aspect_ratio', 16);
            $table->timestamps();
        });

        Schema::create('video_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('tag')->index();
            $table->timestamps();

            $table->unique(['video_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_tags');
        Schema::dropIfExists('video_variants');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('video_categories');
    }
};
