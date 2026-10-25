<?php

use App\Models\AuditLog;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $admin = User::first();
        $adminId = $admin?->id;

        // 1. Initial System Setup Log
        if (! AuditLog::where('action', 'system_init')->exists()) {
            AuditLog::create([
                'user_id' => $adminId,
                'action' => 'system_init',
                'auditable_type' => null,
                'auditable_id' => null,
                'new_values' => [
                    'version' => '1.0.0',
                    'message' => 'Oto-Bot Sosyal Medya Otomasyon Sistemi ve veritabanı başarıyla yapılandırıldı.',
                ],
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        }

        // 2. Admin Login Log
        if ($admin && ! AuditLog::where('action', 'admin_login')->exists()) {
            AuditLog::create([
                'user_id' => $adminId,
                'action' => 'admin_login',
                'auditable_type' => User::class,
                'auditable_id' => $admin->id,
                'new_values' => [
                    'email' => $admin->email,
                    'name' => $admin->name,
                    'guard' => 'web',
                ],
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]);
        }

        // 3. Backfill for existing videos
        if (Schema::hasTable('videos')) {
            $videos = Video::all();
            foreach ($videos as $video) {
                $alreadyLogged = AuditLog::where('action', 'video_uploaded')
                    ->where('auditable_type', Video::class)
                    ->where('auditable_id', $video->id)
                    ->exists();

                if (! $alreadyLogged) {
                    AuditLog::create([
                        'user_id' => $adminId,
                        'action' => 'video_uploaded',
                        'auditable_type' => Video::class,
                        'auditable_id' => $video->id,
                        'new_values' => [
                            'title' => $video->title,
                            'original_filename' => $video->original_filename,
                            'file_size' => $video->file_size,
                            'duration_seconds' => $video->duration_seconds,
                            'aspect_ratio' => $video->aspect_ratio,
                        ],
                        'ip_address' => '127.0.0.1',
                        'created_at' => $video->created_at ?? now(),
                        'updated_at' => $video->updated_at ?? now(),
                    ]);
                }
            }
        }

        // 4. Backfill for existing social accounts
        if (Schema::hasTable('social_accounts')) {
            $accounts = SocialAccount::all();
            foreach ($accounts as $acc) {
                $alreadyLogged = AuditLog::where('action', 'channel_connected')
                    ->where('auditable_type', SocialAccount::class)
                    ->where('auditable_id', $acc->id)
                    ->exists();

                if (! $alreadyLogged) {
                    AuditLog::create([
                        'user_id' => $adminId,
                        'action' => 'channel_connected',
                        'auditable_type' => SocialAccount::class,
                        'auditable_id' => $acc->id,
                        'new_values' => [
                            'platform_id' => $acc->platform_id,
                            'name' => $acc->name,
                            'username' => $acc->username,
                        ],
                        'ip_address' => '127.0.0.1',
                        'created_at' => $acc->created_at ?? now(),
                        'updated_at' => $acc->updated_at ?? now(),
                    ]);
                }
            }
        }

        // 5. Backfill for existing scheduled posts
        if (Schema::hasTable('scheduled_posts')) {
            $posts = ScheduledPost::all();
            foreach ($posts as $post) {
                $alreadyLogged = AuditLog::where('action', 'post_scheduled')
                    ->where('auditable_type', ScheduledPost::class)
                    ->where('auditable_id', $post->id)
                    ->exists();

                if (! $alreadyLogged) {
                    AuditLog::create([
                        'user_id' => $adminId,
                        'action' => 'post_scheduled',
                        'auditable_type' => ScheduledPost::class,
                        'auditable_id' => $post->id,
                        'new_values' => [
                            'video_id' => $post->video_id,
                            'scheduled_at' => (string) $post->scheduled_at,
                            'status' => $post->status?->value ?? (string) $post->status,
                        ],
                        'ip_address' => '127.0.0.1',
                        'created_at' => $post->created_at ?? now(),
                        'updated_at' => $post->updated_at ?? now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // No need to wipe logs on rollback
    }
};
