<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workspace_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('editor'); // super_admin, admin, editor, viewer
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('website')->nullable();
            $table->string('slogan')->nullable();
            $table->string('brand_tone')->default('energetic'); // energetic, professional, casual, playful
            $table->string('target_audience')->nullable();
            $table->string('brand_language')->default('tr');
            $table->json('seo_keywords')->nullable();
            $table->json('forbidden_words')->nullable();
            $table->text('default_cta')->nullable();
            $table->json('landing_urls')->nullable();
            $table->text('campaign_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
        Schema::dropIfExists('workspace_users');
        Schema::dropIfExists('workspaces');
    }
};
