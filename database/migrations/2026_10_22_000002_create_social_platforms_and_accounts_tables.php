<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // facebook, instagram, youtube, x, pinterest, tiktok, linkedin
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('supported_features')->nullable(); // reels, shorts, carousel, video, tags
            $table->integer('daily_post_limit')->default(20);
            $table->json('app_credentials_schema')->nullable();
            $table->timestamps();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_id')->constrained('social_platforms')->cascadeOnDelete();
            $table->string('account_id')->index(); // external platform account id
            $table->string('account_type')->default('page'); // page, profile, channel, business
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('profile_url')->nullable();
            $table->string('status')->default('active'); // active, expired, revoked, pending_approval, rate_limited
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_posted_at')->nullable();
            $table->timestamps();

            $table->unique(['platform_id', 'account_id']);
        });

        Schema::create('social_account_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->text('access_token'); // Encrypted
            $table->text('refresh_token')->nullable(); // Encrypted
            $table->string('token_type')->default('Bearer');
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('extra_data')->nullable(); // Encrypted or serialized metadata
            $table->timestamps();
        });

        Schema::create('social_account_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('permission_name');
            $table->string('status')->default('granted'); // granted, pending_review, declined, expired
            $table->timestamp('verified_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'permission_name'], 'account_permission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_account_permissions');
        Schema::dropIfExists('social_account_credentials');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('social_platforms');
    }
};
