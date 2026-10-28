<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            if (! Schema::hasColumn('live_streams', 'target_platform')) {
                $table->string('target_platform')->default('multi')->after('brand_id'); // youtube, instagram, tiktok, multi
            }
            if (! Schema::hasColumn('live_streams', 'tiktok_enabled')) {
                $table->boolean('tiktok_enabled')->default(false)->after('instagram_stream_key');
            }
            if (! Schema::hasColumn('live_streams', 'tiktok_account_id')) {
                $table->foreignId('tiktok_account_id')->nullable()->after('tiktok_enabled')->constrained('social_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('live_streams', 'tiktok_rtmp_url')) {
                $table->string('tiktok_rtmp_url')->default('rtmp://live-push.tiktok.com/live/')->after('tiktok_account_id');
            }
            if (! Schema::hasColumn('live_streams', 'tiktok_stream_key')) {
                $table->text('tiktok_stream_key')->nullable()->after('tiktok_rtmp_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tiktok_account_id');
            $table->dropColumn([
                'target_platform',
                'tiktok_enabled',
                'tiktok_rtmp_url',
                'tiktok_stream_key',
            ]);
        });
    }
};
