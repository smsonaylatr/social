<?php

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
        Schema::table('hashtags', function (Blueprint $table) {
            $table->boolean('is_trending')->default(false)->after('category')->index();
            $table->timestamp('expires_at')->nullable()->after('last_used_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hashtags', function (Blueprint $table) {
            $table->dropIndex(['is_trending']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['is_trending', 'expires_at']);
        });
    }
};
