<?php

namespace App\Services\LiveStream;

use App\Models\AuditLog;
use App\Models\LiveStream;
use App\Models\SocialAccount;
use App\Services\Media\VideoProcessingService;
use App\Services\Social\Publishers\YouTubePublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LiveStreamService
{
    protected string $ffmpegPath;

    public function __construct()
    {
        $this->ffmpegPath = app(VideoProcessingService::class)->resolveBinary('ffmpeg');
    }

    /**
     * Sunucuda FFmpeg'in kurulu ve çalıştırılabilir olup olmadığını kontrol eder.
     */
    public function isFfmpegAvailable(): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }

        $cmd = escapeshellcmd($this->ffmpegPath) . ' -version 2>&1';
        $output = @shell_exec($cmd);

        return ! empty($output) && str_contains(strtolower($output), 'ffmpeg version');
    }

    /**
     * FFmpeg sürüm bilgisini döndürür.
     */
    public function getFfmpegVersion(): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $output = @shell_exec(escapeshellcmd($this->ffmpegPath) . ' -version 2>&1');
        if (! $output) {
            return null;
        }

        $lines = explode("\n", trim($output));
        return trim($lines[0] ?? '');
    }

    /**
     * Yayına ait ffconcat (band kaydı listesi) dosyasını oluşturur veya günceller.
     */
    public function buildConcatFile(LiveStream $stream): string
    {
        $dir = storage_path('app/live-streams');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $videos = $stream->getEligibleVideos();
        if ($videos->isEmpty()) {
            throw new \RuntimeException('Video havuzunda canlı yayın için uygun (Yayına Hazır ve diskte mevcut) video bulunamadı.');
        }

        $stream->update([
            'total_videos_count' => $videos->count(),
        ]);

        $lines = ['ffconcat version 1.0'];

        // Eğer video sayısı azsa (örn. < 5), FFmpeg'in tampon (buffer) geçişlerini pürüzsüz yapması için
        // liste içinde de birkaç kez tekrarlayarak en az 10 satırlık bir blok oluşturuyoruz.
        $repeatFactor = $videos->count() < 5 ? max(1, (int) ceil(10 / $videos->count())) : 1;

        for ($r = 0; $r < $repeatFactor; $r++) {
            foreach ($videos as $video) {
                $absPath = $this->ensureVideoHasAudio($video);
                // FFmpeg concat demuxer için ters eğik çizgileri düz çizgiye çevir ve tek tırnakları kaçır
                $normalizedPath = str_replace('\\', '/', $absPath);
                $escapedPath = str_replace("'", "'\\''", $normalizedPath);

                $lines[] = "file '{$escapedPath}'";
            }
        }

        $concatFilePath = "{$dir}/stream_{$stream->id}_playlist.txt";
        file_put_contents($concatFilePath, implode("\n", $lines) . "\n");

        return $concatFilePath;
    }

    /**
     * Aktif tüm yayınların concat listesini (yeni video yüklendiğinde/silindiğinde) kesintisiz günceller.
     */
    public function refreshActiveConcatFiles(): int
    {
        $activeStreams = LiveStream::whereIn('status', ['running', 'starting', 'restarting'])->get();
        $count = 0;

        foreach ($activeStreams as $stream) {
            try {
                $this->buildConcatFile($stream);
                $count++;
            } catch (\Throwable $e) {
                Log::warning("Canlı yayın #{$stream->id} concat listesi güncellenemedi: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Canlı yayın için çalıştırılacak tam FFmpeg komutunu oluşturur.
     */
    public function buildFfmpegCommand(LiveStream $stream, ?string $concatFilePath = null): string
    {
        $concatFilePath = $concatFilePath ?: $this->buildConcatFile($stream);
        $endpoints = $stream->getActiveRtmpEndpoints();

        if (empty($endpoints)) {
            throw new \RuntimeException('En az bir hedef platform (YouTube veya Instagram) etkinleştirilmeli ve Yayın Anahtarı (Stream Key) girilmelidir.');
        }

        $loopFlag = $stream->is_loop ? '-stream_loop -1' : '';
        $fps = max(15, min(60, (int) ($stream->fps ?: 30)));
        $gop = $fps * 2; // 2 saniyede bir keyframe (YouTube & Instagram zorunlu standardı)
        $bitrate = max(800, min(8000, (int) ($stream->bitrate_kbps ?: 2500)));
        $bufsize = $bitrate * 2;

        $normalizedConcatPath = str_replace('\\', '/', $concatFilePath);

        // Giriş parametreleri:
        // +genpts+igndts sayesinde farklı videolar birbirine bağlanırken zaman damgası (PTS/DTS) kesintisi yaşanmaz.
        $inputArgs = sprintf(
            '-fflags +genpts+igndts -re %s -f concat -safe 0 -i %s',
            $loopFlag,
            escapeshellarg($normalizedConcatPath)
        );

        // Eğer orijinal sesi kapat seçildiyse sessiz stereo AAC kaynağı ekle
        if ($stream->mute_original_audio) {
            $inputArgs .= ' -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100';
        }

        // Video filtre ve kodlama ayarları
        if ($stream->resolution_mode === 'copy') {
            $videoCodecArgs = '-c:v copy';
        } else {
            [$targetW, $targetH] = match ($stream->resolution_mode) {
                'vertical_720p' => [720, 1280],
                'horizontal_1080p' => [1920, 1080],
                'horizontal_720p' => [1280, 720],
                default => [1080, 1920], // vertical_1080p (Instagram Live & YouTube Shorts/Dikey Canlı Yayın)
            };

            $vf = sprintf(
                'scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black,setsar=1,fps=%d,format=yuv420p',
                $targetW,
                $targetH,
                $targetW,
                $targetH,
                $fps
            );

            $videoCodecArgs = sprintf(
                '-vf %s -c:v libx264 -preset veryfast -tune zerolatency -b:v %dk -maxrate %dk -bufsize %dk -r %d -g %d -keyint_min %d -sc_threshold 0 -pix_fmt yuv420p',
                escapeshellarg($vf),
                $bitrate,
                $bitrate,
                $bufsize,
                $fps,
                $gop,
                $gop
            );
        }

        // Ses kodlama ayarları
        $audioMapArgs = $stream->mute_original_audio
            ? '-map 0:v:0 -map 1:a:0'
            : '';

        $audioCodecArgs = '-c:a aac -b:a 128k -ar 44100 -ac 2';

        // Çıkış (Tekil RTMP veya Eşzamanlı Çoklu Platform Tee Muxer)
        $endpointValues = array_values($endpoints);

        if (count($endpointValues) === 1) {
            $outputArgs = sprintf(
                '-f flv -flvflags no_duration_filesize %s',
                escapeshellarg($endpointValues[0])
            );
        } else {
            // Aynı anda hem YouTube hem Instagram (Simulcast):
            // Tek kez encode edilir, tee muxer ile tüm RTMP hedeflerine eşzamanlı gönderilir.
            // onfail=ignore sayesinde bir platform (örn. Instagram 4 saat limiti) kapansa bile YouTube yayını kesilmez!
            $teeTargets = array_map(function (string $url) {
                return '[f=flv:onfail=ignore]' . $url;
            }, $endpointValues);

            $teeString = implode('|', $teeTargets);
            $mapForTee = $stream->mute_original_audio ? '' : '-map 0:v -map 0:a?';

            $outputArgs = sprintf(
                '%s -flags +global_header -f tee %s',
                $mapForTee,
                escapeshellarg($teeString)
            );
        }

        return trim(sprintf(
            '%s -y %s %s %s %s %s',
            escapeshellcmd($this->ffmpegPath),
            $inputArgs,
            $audioMapArgs,
            $videoCodecArgs,
            $audioCodecArgs,
            $outputArgs
        ));
    }

    /**
     * Canlı yayını arka planda (daemon process olarak) başlatır.
     */
    public function startStream(LiveStream $stream): bool
    {
        // Zaten çalışıyorsa önce mevcut süreci temizle
        if ($stream->process_pid && $this->isProcessRunning($stream->process_pid)) {
            $this->killProcess($stream->process_pid);
        }

        $concatFile = $this->buildConcatFile($stream);
        $ffmpegCommand = $this->buildFfmpegCommand($stream, $concatFile);

        $logDir = storage_path('logs/live-streams');
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = "{$logDir}/stream_{$stream->id}.log";
        $headerLog = sprintf(
            "[%s] === SOCIALFLOW 7/24 CANLI YAYIN BAŞLATILIYOR (#%d - %s) ===\nKomut: %s\n------------------------------------------------------------\n",
            now()->toDateTimeString(),
            $stream->id,
            $stream->title,
            $ffmpegCommand
        );
        file_put_contents($logFile, $headerLog);

        if (! $this->isFfmpegAvailable()) {
            $stream->update([
                'status' => 'error',
                'last_error' => 'Sunucuda FFmpeg kurulu değil veya shell_exec devre dışı. Lütfen sunucuda "apt-get install -y ffmpeg" komutunu çalıştırın.',
            ]);
            throw new \RuntimeException('Sunucuda FFmpeg bulunamadı. Canlı yayın akışı için sunucuda FFmpeg kurulu olmalıdır.');
        }

        $pid = $this->spawnBackgroundProcess($ffmpegCommand, $logFile);

        if (! $pid || $pid <= 0) {
            $stream->update([
                'status' => 'error',
                'last_error' => 'FFmpeg arka plan süreci başlatılamadı (PID alınamadı).',
            ]);
            throw new \RuntimeException('FFmpeg arka plan süreci başlatılamadı.');
        }

        // 1 saniye bekleyip sürecin hemen hata verip kapanmadığını doğrula
        usleep(800000);
        if (! $this->isProcessRunning($pid)) {
            $recentLog = $this->getRecentLogs($stream, 15);
            $stream->update([
                'status' => 'error',
                'process_pid' => null,
                'last_error' => "FFmpeg başlatıldıktan hemen sonra kapandı. Lütfen RTMP Yayın Anahtarınızı kontrol edin.\n" . $recentLog,
            ]);
            throw new \RuntimeException('Yayın başlatılamadı. RTMP Yayın Anahtarı geçersiz olabilir veya bağlantı reddedildi.');
        }

        $stream->update([
            'status' => 'running',
            'process_pid' => $pid,
            'started_at' => $stream->started_at ?: now(),
            'stopped_at' => null,
            'last_heartbeat_at' => now(),
            'last_error' => null,
        ]);

        AuditLog::record('live_stream_started', $stream, [
            'title' => $stream->title,
            'pid' => $pid,
            'targets' => array_keys($stream->getActiveRtmpEndpoints()),
            'videos_count' => $stream->total_videos_count,
        ]);

        return true;
    }

    /**
     * Çalışan canlı yayını güvenli şekilde durdurur.
     */
    public function stopStream(LiveStream $stream): bool
    {
        if ($stream->process_pid) {
            $this->killProcess($stream->process_pid);
        }

        $logFile = $this->getLogFilePath($stream);
        if (file_exists($logFile)) {
            @file_put_contents(
                $logFile,
                sprintf("\n[%s] === YAYIN KULLANICI TARAFINDAN DURDURULDU ===\n", now()->toDateTimeString()),
                FILE_APPEND
            );
        }

        $stream->update([
            'status' => 'stopped',
            'process_pid' => null,
            'stopped_at' => now(),
        ]);

        AuditLog::record('live_stream_stopped', $stream, [
            'title' => $stream->title,
        ]);

        return true;
    }

    /**
     * Watchdog: Durumu 'running' olan yayınları kontrol eder;
     * süreç çökmüş veya bağlantı kopmuşsa ve auto_restart açıksa otomatik yeniden başlatır.
     */
    public function checkHealthAndWatchdog(): array
    {
        $runningStreams = LiveStream::whereIn('status', ['running', 'restarting'])->get();
        $stats = [
            'checked' => $runningStreams->count(),
            'healthy' => 0,
            'restarted' => 0,
            'failed' => 0,
        ];

        foreach ($runningStreams as $stream) {
            if ($stream->process_pid && $this->isProcessRunning($stream->process_pid)) {
                $stream->update(['last_heartbeat_at' => now()]);
                $stats['healthy']++;
                continue;
            }

            // Süreç kapanmış!
            if (! $stream->auto_restart) {
                $stream->update([
                    'status' => 'stopped',
                    'process_pid' => null,
                    'stopped_at' => now(),
                    'last_error' => 'Yayın süreci sonlandı (Otomatik yeniden başlatma kapalı).',
                ]);
                $stats['failed']++;
                continue;
            }

            // Otomatik yeniden başlat (Self-Healing)
            try {
                $stream->update([
                    'status' => 'restarting',
                    'restart_count' => $stream->restart_count + 1,
                ]);

                $this->startStream($stream);
                $stats['restarted']++;
            } catch (\Throwable $e) {
                $stream->update([
                    'status' => 'error',
                    'process_pid' => null,
                    'last_error' => 'Watchdog otomatik yeniden başlatma hatası: ' . $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Bağlı YouTube OAuth hesabı üzerinden YouTube Data API v3 ile
     * otomatik Canlı Yayın (liveBroadcast) ve RTMP Anahtarı (liveStream) oluşturur.
     */
    public function provisionYouTubeLiveStream(LiveStream $stream, ?int $accountId = null): array
    {
        $accountId = $accountId ?: $stream->youtube_account_id;
        $account = $accountId
            ? SocialAccount::with(['credential', 'platform'])->find($accountId)
            : SocialAccount::with(['credential', 'platform'])
                ->whereHas('platform', fn ($q) => $q->where('code', 'youtube'))
                ->where('status', 'active')
                ->first();

        if (! $account || ! $account->credential) {
            throw new \RuntimeException('Bağlı aktif bir YouTube kanalı bulunamadı. Lütfen önce Sosyal Medya Kanalları sayfasından YouTube hesabınızı bağlayın veya doğrudan YouTube Studio Yayın Anahtarınızı yapıştırın.');
        }

        $token = $account->credential->access_token;

        // Sandbox veya Partner test ortamı kontrolü
        if (str_starts_with((string) $token, 'partner_oauth_') || str_starts_with((string) $token, 'sandbox_')) {
            $mockKey = 'yt-live-' . substr(md5((string) $stream->id . uniqid()), 0, 4) . '-' . substr(md5(uniqid()), 0, 4) . '-' . substr(md5(uniqid()), 0, 4);
            $mockBroadcastId = 'live_' . substr(md5(uniqid()), 0, 11);

            $stream->update([
                'youtube_account_id' => $account->id,
                'youtube_rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2',
                'youtube_stream_key' => $mockKey,
                'youtube_broadcast_id' => $mockBroadcastId,
                'youtube_watch_url' => "https://www.youtube.com/watch?v={$mockBroadcastId}",
            ]);

            return [
                'rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2',
                'stream_key' => $mockKey,
                'broadcast_id' => $mockBroadcastId,
                'watch_url' => "https://www.youtube.com/watch?v={$mockBroadcastId}",
                'is_sandbox' => true,
            ];
        }

        // Token süresi dolmuşsa yenile
        if ($account->credential->token_expires_at && $account->credential->token_expires_at->isPast()) {
            app(YouTubePublisher::class)->refreshToken($account);
            $account->load('credential');
            $token = $account->credential->access_token;
        }

        // 1. YouTube LiveStream (RTMP Ingest) oluştur
        $streamResponse = Http::withToken($token)
            ->post('https://www.googleapis.com/youtube/v3/liveStreams?part=snippet,cdn,contentDetails', [
                'snippet' => [
                    'title' => $stream->title . ' - Stream Ingest',
                    'description' => 'SocialFlow AI 7/24 Otomatik Band Yayını Akışı',
                ],
                'cdn' => [
                    'frameRate' => 'variable',
                    'ingestionType' => 'rtmp',
                    'resolution' => 'variable',
                ],
                'contentDetails' => [
                    'isReusable' => true,
                ],
            ]);

        if (! $streamResponse->successful()) {
            $body = $streamResponse->body();
            if (str_contains($body, 'insufficientPermissions') || str_contains($body, 'ACCESS_TOKEN_SCOPE_INSUFFICIENT')) {
                throw new \RuntimeException('Mevcut YouTube OAuth izniniz yalnızca video yüklemeyi (youtube.upload) kapsıyor. Canlı yayın anahtarını otomatik çekmek için YouTube Studio -> Canlı Yayın ekranındaki "Yayın Anahtarı (Stream Key)" kodunu kopyalayıp aşağıdaki alana doğrudan yapıştırabilirsiniz.');
            }
            if (str_contains($body, 'liveStreamingNotEnabled')) {
                throw new \RuntimeException('Bu YouTube kanalında henüz "Canlı Yayın" özelliği etkinleştirilmemiş. Lütfen YouTube Studio üzerinden telefon doğrulaması yaparak Canlı Yayın özelliğini açın.');
            }
            throw new \RuntimeException('YouTube Live API Hatası: ' . $body);
        }

        $ytStreamId = $streamResponse->json('id');
        $ingestionInfo = $streamResponse->json('cdn.ingestionInfo', []);
        $rtmpUrl = $ingestionInfo['ingestionAddress'] ?? 'rtmp://a.rtmp.youtube.com/live2';
        $streamKey = $ingestionInfo['streamName'] ?? '';

        // 2. YouTube LiveBroadcast oluştur (Otomatik Başlatma açık)
        $broadcastResponse = Http::withToken($token)
            ->post('https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet,status,contentDetails', [
                'snippet' => [
                    'title' => $stream->title,
                    'description' => $stream->description ?: '7/24 Kesintisiz Canlı Yayın',
                    'scheduledStartTime' => now()->toIso8601String(),
                ],
                'status' => [
                    'privacyStatus' => 'public',
                    'selfDeclaredMadeForKids' => false,
                ],
                'contentDetails' => [
                    'enableAutoStart' => true,
                    'enableAutoStop' => false,
                    'enableDvr' => true,
                    'monitorStream' => [
                        'enableMonitorStream' => false,
                    ],
                ],
            ]);

        $broadcastId = $broadcastResponse->json('id');

        // 3. Broadcast ile Stream'i birbirine bağla
        if ($broadcastId && $ytStreamId) {
            Http::withToken($token)->post(
                "https://www.googleapis.com/youtube/v3/liveBroadcasts/bind?id={$broadcastId}&part=id,contentDetails&streamId={$ytStreamId}"
            );
        }

        $watchUrl = $broadcastId ? "https://www.youtube.com/watch?v={$broadcastId}" : null;

        $stream->update([
            'youtube_account_id' => $account->id,
            'youtube_rtmp_url' => $rtmpUrl,
            'youtube_stream_key' => $streamKey,
            'youtube_broadcast_id' => $broadcastId,
            'youtube_watch_url' => $watchUrl,
        ]);

        return [
            'rtmp_url' => $rtmpUrl,
            'stream_key' => $streamKey,
            'broadcast_id' => $broadcastId,
            'watch_url' => $watchUrl,
            'is_sandbox' => false,
        ];
    }

    /**
     * Yayına ait log dosyasının tam yolunu döndürür.
     */
    public function getLogFilePath(LiveStream $stream): string
    {
        return storage_path("logs/live-streams/stream_{$stream->id}.log");
    }

    /**
     * FFmpeg canlı yayın log dosyasının son N satırını döndürür.
     */
    public function getRecentLogs(LiveStream $stream, int $maxLines = 45): string
    {
        $logFile = $this->getLogFilePath($stream);
        if (! file_exists($logFile)) {
            return 'Henüz yayın günlüğü (log) oluşmadı.';
        }

        $size = filesize($logFile);
        if ($size === 0) {
            return 'Log dosyası boş.';
        }

        // Son 32 KB veriyi oku
        $readBytes = min($size, 32768);
        $fp = @fopen($logFile, 'rb');
        if (! $fp) {
            return 'Log dosyası okunamadı.';
        }

        fseek($fp, -$readBytes, SEEK_END);
        $content = fread($fp, $readBytes);
        fclose($fp);

        // FFmpeg ilerleme satırlarında \r kullanır, bunları \n'e dönüştür
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        $lines = array_values(array_filter(explode("\n", $content), fn ($l) => trim($l) !== ''));
        $slice = array_slice($lines, -$maxLines);

        return implode("\n", $slice);
    }

    /**
     * Videoda ses parçası (audio track) olup olmadığını denetler.
     */
    public function hasAudioTrack(string $filePath): bool
    {
        if (! file_exists($filePath) || filesize($filePath) < 16) {
            return false;
        }

        // 1. Hızlı pure-PHP MP4 atom taraması ('soun' veya 'mp4a' handler)
        $fp = @fopen($filePath, 'rb');
        if ($fp) {
            $sample = fread($fp, min(filesize($filePath), 2 * 1024 * 1024));
            fclose($fp);
            if (str_contains($sample, 'soun') || str_contains($sample, 'mp4a')) {
                return true;
            }
        }

        // 2. FFprobe ile kesin doğrulama
        $ffprobe = app(VideoProcessingService::class)->resolveBinary('ffprobe');
        if ($ffprobe && function_exists('shell_exec')) {
            $cmd = sprintf(
                '%s -v error -select_streams a -show_entries stream=codec_type -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
                escapeshellcmd($ffprobe),
                escapeshellarg($filePath)
            );
            $out = trim((string) @shell_exec($cmd));
            if (str_contains(strtolower($out), 'audio')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Concat demuxer uyumluluğu için videonun ses akışı içerdiğini garanti eder.
     * Ses yoksa orijinal videoyu yeniden kodlamadan (-c:v copy) saniyenin onda birinde sessiz AAC ekler.
     */
    public function ensureVideoHasAudio(\App\Models\Video $video): string
    {
        $disk = $video->disk ?: 'public';
        $absPath = Storage::disk($disk)->path($video->storage_path);

        if (! file_exists($absPath)) {
            return $absPath;
        }

        if ($this->hasAudioTrack($absPath)) {
            return $absPath;
        }

        // Ses kanalı yok! Concat demuxer'ın çökmemesi için sessiz AAC eşlik dosyası oluştur
        $cacheDir = storage_path('app/live-streams/audio-fixed');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $fixedFile = $cacheDir . '/fixed_' . pathinfo($video->storage_path, PATHINFO_FILENAME) . '.mp4';
        if (file_exists($fixedFile) && filesize($fixedFile) > 1000) {
            return $fixedFile;
        }

        if (! $this->isFfmpegAvailable()) {
            return $absPath;
        }

        $cmd = sprintf(
            '%s -y -i %s -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100 -c:v copy -c:a aac -b:a 128k -ar 44100 -shortest -map 0:v:0 -map 1:a:0 %s 2>&1',
            escapeshellcmd($this->ffmpegPath),
            escapeshellarg($absPath),
            escapeshellarg($fixedFile)
        );
        @shell_exec($cmd);

        if (file_exists($fixedFile) && filesize($fixedFile) > 1000) {
            return $fixedFile;
        }

        return $absPath;
    }

    /**
     * İşletim sistemine (Linux / Windows) uygun şekilde arka plan işlemi başlatır ve PID döndürür.
     */
    protected function spawnBackgroundProcess(string $command, string $logFile): ?int
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: WMIC process call create (Yerel ve ayrık süreç başlatır, ProcessId döndürür)
            $wmicCmd = sprintf(
                'wmic process call create "cmd.exe /c %s >> %s 2>&1" 2>&1',
                $command,
                escapeshellarg($logFile)
            );
            $output = (string) @shell_exec($wmicCmd);
            if (preg_match('/ProcessId\s*=\s*(\d+)/', $output, $matches)) {
                return (int) $matches[1];
            }

            // Fallback: PowerShell Start-Process
            $batDir = storage_path('app/live-streams');
            if (! is_dir($batDir)) {
                @mkdir($batDir, 0775, true);
            }
            $runnerFile = $batDir . '/run_' . md5($command . uniqid()) . '.bat';
            file_put_contents($runnerFile, "@echo off\r\n" . $command . " >> " . escapeshellarg($logFile) . " 2>&1\r\n");

            $psCmd = sprintf(
                'powershell -NoProfile -Command "(Start-Process -FilePath \'cmd.exe\' -ArgumentList \'/c \"\"%s\"\"\' -WindowStyle Hidden -PassThru).Id"',
                $runnerFile
            );
            $output = trim((string) @shell_exec($psCmd));
            if (ctype_digit($output) && (int) $output > 0) {
                return (int) $output;
            }

            return null;
        }

        // Linux / Unix (Ubuntu, Debian, CentOS, Plesk, cPanel)
        $bgCmd = sprintf(
            'nohup %s >> %s 2>&1 & echo $!',
            $command,
            escapeshellarg($logFile)
        );

        $output = trim((string) @shell_exec($bgCmd));
        $lines = explode("\n", $output);
        $lastLine = trim((string) end($lines));

        if (ctype_digit($lastLine) && (int) $lastLine > 0) {
            return (int) $lastLine;
        }

        return null;
    }

    /**
     * Verilen PID numaralı sürecin hâlâ çalışıp çalışmadığını kontrol eder.
     */
    public function isProcessRunning(?int $pid): bool
    {
        if (! $pid || $pid <= 0) {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $output = (string) @shell_exec("tasklist /FI \"PID eq {$pid}\" /NH 2>nul");
            return str_contains($output, (string) $pid);
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (@file_exists("/proc/{$pid}")) {
            return true;
        }

        $output = trim((string) @shell_exec("ps -p {$pid} -o pid= 2>/dev/null"));
        return $output === (string) $pid;
    }

    /**
     * Verilen PID numaralı süreci ve alt süreçlerini sonlandırır.
     */
    public function killProcess(?int $pid): void
    {
        if (! $pid || $pid <= 0) {
            return;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            @shell_exec("taskkill /F /PID {$pid} /T 2>nul");
            return;
        }

        @shell_exec("pkill -TERM -P {$pid} 2>/dev/null");
        @shell_exec("kill -15 {$pid} 2>/dev/null");
        usleep(300000);
        if ($this->isProcessRunning($pid)) {
            @shell_exec("pkill -KILL -P {$pid} 2>/dev/null");
            @shell_exec("kill -9 {$pid} 2>/dev/null");
        }
    }
}
