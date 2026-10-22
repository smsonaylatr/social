<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform_code')->default('all'); // all, facebook, instagram, youtube, x, pinterest, tiktok, linkedin
            $table->string('content_type')->default('reels'); // reels, post, shorts, pin, tweet
            $table->text('system_prompt');
            $table->text('user_prompt_template');
            $table->string('ai_model')->default('gpt-4o-mini');
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_content_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform_code'); // instagram, facebook, youtube, x, pinterest, tiktok, linkedin
            $table->string('title')->nullable();
            $table->text('hook_sentence')->nullable();
            $table->text('caption');
            $table->json('hashtags')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->text('call_to_action')->nullable();
            $table->text('suggested_product_url')->nullable();
            $table->string('ai_model')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost_usd', 8, 6)->default(0.000000);
            $table->string('status')->default('draft'); // draft, approved, edited, rejected
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['video_id', 'platform_code']);
        });

        Schema::create('hashtag_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->default('brand'); // brand, product, general, trending, kids, gift, education, campaign, seasonal
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('hashtag_groups')->nullOnDelete();
            $table->string('tag')->index();
            $table->string('category')->default('general');
            $table->string('platform_filter')->nullable(); // null for all, or specific platform
            $table->unsignedInteger('priority')->default(5);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'tag']);
        });

        Schema::create('keyword_library', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keyword')->index();
            $table->string('language', 5)->default('tr');
            $table->string('category')->default('product');
            $table->string('search_intent')->default('commercial'); // informational, navigational, commercial, transactional
            $table->unsignedInteger('priority')->default(5);
            $table->string('target_url')->nullable();
            $table->string('platform')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_library');
        Schema::dropIfExists('hashtags');
        Schema::dropIfExists('hashtag_groups');
        Schema::dropIfExists('ai_content_generations');
        Schema::dropIfExists('ai_prompt_templates');
    }
};
