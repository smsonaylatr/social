<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            if (! Schema::hasColumn('live_streams', 'facebook_enabled')) {
                $table->boolean('facebook_enabled')->default(false)->after('tiktok_stream_key');
            }
            if (! Schema::hasColumn('live_streams', 'facebook_account_id')) {
                $table->foreignId('facebook_account_id')->nullable()->after('facebook_enabled')->constrained('social_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('live_streams', 'facebook_rtmp_url')) {
                $table->string('facebook_rtmp_url')->default('rtmps://live-api-s.facebook.com:443/rtmp/')->after('facebook_account_id');
            }
            if (! Schema::hasColumn('live_streams', 'facebook_stream_key')) {
                $table->text('facebook_stream_key')->nullable()->after('facebook_rtmp_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facebook_account_id');
            $table->dropColumn([
                'facebook_enabled',
                'facebook_rtmp_url',
                'facebook_stream_key',
            ]);
        });
    }
};
