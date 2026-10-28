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
     * Sunucuda (özellikle Linux VPS / cPanel / Plesk) kurulu FFmpeg yoksa
     * bağımsız x86_64 statik ikiliyi (binary) storage/app/bin altına otomatik indirip kurar.
     */
    public function autoInstallFfmpeg(): array
    {
        $binDir = storage_path('app/bin');
        if (! is_dir($binDir)) {
            @mkdir($binDir, 0775, true);
        }

        $ffmpegDest = $binDir . '/ffmpeg';
        $ffprobeDest = $binDir . '/ffprobe';

        if (PHP_OS_FAMILY === 'Linux') {
            $urls = [
                'ffmpeg' => [
                    'https://github.com/eugeneware/ffmpeg-static/releases/download/b6.1/ffmpeg-linux-x64',
                    'https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v6.1/ffmpeg-6.1-linux-64.zip',
                ],
                'ffprobe' => [
                    'https://github.com/eugeneware/ffmpeg-static/releases/download/b6.1/ffprobe-linux-x64',
                    'https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v6.1/ffprobe-6.1-linux-64.zip',
                ],
            ];

            // 1. FFmpeg İndir
            if (! file_exists($ffmpegDest) || filesize($ffmpegDest) < 1000000) {
                $downloaded = false;
                try {
                    $ctx = stream_context_create([
                        'http' => ['follow_location' => 1, 'timeout' => 90],
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                    ]);
                    $content = @file_get_contents($urls['ffmpeg'][0], false, $ctx);
                    if ($content && strlen($content) > 5000000) {
                        file_put_contents($ffmpegDest, $content);
                        @chmod($ffmpegDest, 0755);
                        $downloaded = true;
                    }
                } catch (\Throwable) {}

                if (! $downloaded) {
                    $zipPath = $binDir . '/ffmpeg.zip';
                    $zipContent = @file_get_contents($urls['ffmpeg'][1], false, stream_context_create([
                        'http' => ['follow_location' => 1, 'timeout' => 90],
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                    ]));
                    if ($zipContent && strlen($zipContent) > 1000000) {
                        file_put_contents($zipPath, $zipContent);
                        if (class_exists('ZipArchive')) {
                            $zip = new \ZipArchive();
                            if ($zip->open($zipPath) === true) {
                                $zip->extractTo($binDir);
                                $zip->close();
                                @chmod($ffmpegDest, 0755);
                                $downloaded = true;
                            }
                        }
                        @unlink($zipPath);
                    }
                }
            }

            // 2. FFprobe İndir
            if (! file_exists($ffprobeDest) || filesize($ffprobeDest) < 1000000) {
                try {
                    $ctx = stream_context_create([
                        'http' => ['follow_location' => 1, 'timeout' => 90],
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                    ]);
                    $probeContent = @file_get_contents($urls['ffprobe'][0], false, $ctx);
                    if ($probeContent && strlen($probeContent) > 3000000) {
                        file_put_contents($ffprobeDest, $probeContent);
                        @chmod($ffprobeDest, 0755);
                    }
                } catch (\Throwable) {}
            }
        }

        @chmod($ffmpegDest, 0755);
        @chmod($ffprobeDest, 0755);

        $versionOutput = @shell_exec("{$ffmpegDest} -version 2>&1");
        $isOk = $versionOutput && str_contains(strtolower($versionOutput), 'ffmpeg version');

        if ($isOk) {
            \App\Models\Setting::set('ffmpeg_binary_path', $ffmpegDest);
            \App\Models\Setting::set('ffprobe_binary_path', $ffprobeDest);
            $this->ffmpegPath = $ffmpegDest;
        }

        return [
            'success' => $isOk,
            'binary_path' => $ffmpegDest,
            'version' => $versionOutput ? explode("\n", $versionOutput)[0] : 'Çalıştırılamadı',
        ];
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
            throw new \RuntimeException('En az bir hedef platform (YouTube, Instagram veya TikTok) etkinleştirilmeli ve Yayın Anahtarı (Stream Key) girilmelidir.');
        }

        $loopFlag = $stream->is_loop ? '-stream_loop -1' : '';
        $fps = max(15, min(60, (int) ($stream->fps ?: 30)));
        $gop = $fps * 2; // 2 saniyede bir keyframe (YouTube & Instagram & TikTok zorunlu standardı)
        $bitrate = max(800, min(8000, (int) ($stream->bitrate_kbps ?: 2500)));

        $normalizedConcatPath = str_replace('\\', '/', $concatFilePath);

        // Giriş parametreleri:
        // -nostdin arka plan sürecinin terminal stdin'e takılmasını önler.
        // -fflags +genpts+discardcorrupt ve ignore_err sayesinde zaman damgaları (PTS) baştan itibaren kusursuz oluşturulur,
        // bozuk veya eksik karelerde süreç donmaz veya takılmaz.
        $inputArgs = sprintf(
            '-nostdin -fflags +genpts+discardcorrupt -err_detect ignore_err -re %s -f concat -safe 0 -protocol_whitelist file,crypto,data,http,https,tcp,tls -i %s',
            $loopFlag,
            escapeshellarg($normalizedConcatPath)
        );

        // Ses Modu ve Telifsiz Fon Müziği Yönetimi:
        // 'original': Orijinal video sesi
        // 'replace_bg_music': Orijinal sesi kapat, telifsiz fon müziği çal (Telif koruması)
        // 'mix_bg_music': Orijinal ses + telifsiz fon müziğini karıştır (Mikser)
        // 'mute': Tamamen sessiz
        $audioMode = $stream->audio_mode ?: ($stream->mute_original_audio ? 'mute' : 'original');

        // Kullanıcı mikser modunu seçip orijinal sesi 0 yaptıysa doğrudan replace_bg_music uygula
        // Böylece video havuzundaki farklı ses codec'leri (HE-AAC vs LC) çözücüyü asla kilitleyemez.
        if ($audioMode === 'mix_bg_music' && (int) ($stream->original_audio_volume ?? 100) <= 0) {
            $audioMode = 'replace_bg_music';
        }

        $hasBgMusic = in_array($audioMode, ['replace_bg_music', 'mix_bg_music'], true);
        $musicFile = $hasBgMusic ? $this->resolveBackgroundMusicPath($stream) : null;
        $musicFileExists = $musicFile && file_exists($musicFile);

        if ($hasBgMusic && ! $musicFileExists) {
            $audioMode = $stream->mute_original_audio ? 'mute' : 'original';
            $hasBgMusic = false;
        }

        if ($audioMode === 'mute') {
            $inputArgs .= ' -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100';
        } elseif ($hasBgMusic && $musicFileExists) {
            $normalizedMusicPath = str_replace('\\', '/', $musicFile);
            $inputArgs .= sprintf(' -stream_loop -1 -i %s', escapeshellarg($normalizedMusicPath));
        }

        // Çözünürlük ve Video Filtresi
        [$targetW, $targetH, $defaultBitrate] = match ($stream->resolution_mode) {
            'vertical_1080p' => [1080, 1920, 3000],
            'horizontal_1080p' => [1920, 1080, 3500],
            'horizontal_720p' => [1280, 720, 2500],
            default => [720, 1280, 2500], // vertical_720p (Instagram Live Producer standardı: 2250-4000 kbps)
        };

        $bitrate = max(2250, min(8000, (int) ($stream->bitrate_kbps ?: $defaultBitrate)));

        $vf = sprintf(
            'scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black,setsar=1,fps=%d,setpts=N/%d/TB,format=yuv420p',
            $targetW,
            $targetH,
            $targetW,
            $targetH,
            $fps,
            $fps
        );

        // Instagram Live, TikTok ve YouTube standartlarına %100 uyumlu H.264 parametreleri:
        // -profile:v main mobil oynatıcı uyumluluğunu garantiler.
        // -flags +cgop ve sabit GOP (2 sn) HLS paketleyici segmentasyonunu kusursuz yapar ("Yüklemede sorun yaşıyoruz" hatasını önler).
        $videoCodecArgs = sprintf(
            '-c:v libx264 -profile:v main -preset ultrafast -tune zerolatency -threads 0 -b:v %dk -minrate %dk -maxrate %dk -bufsize %dk -fps_mode cfr -g %d -keyint_min %d -sc_threshold 0 -flags +cgop -pix_fmt yuv420p',
            $bitrate,
            $bitrate,
            $bitrate,
            $bitrate * 2,
            $gop,
            $gop
        );

        $audioCodecArgs = '-c:a aac -b:a 128k -ar 44100 -ac 2';
        $filterAndMapArgs = '';

        if ($audioMode === 'mix_bg_music' && $musicFileExists) {
            // Mikser Modu: Orijinal Ses + Fon Müziği
            // Girdi formatı mono/stereo veya 8kHz/48kHz olsa dahi kesin olarak 44.1kHz Stereo'ya normalize edilir.
            // asetpts=N/SR/TB ile zamanda geriye düşme (non-monotonic DTS) tamamen imkansız kılınır.
            $origVol = max(0.0, min(1.0, ($stream->original_audio_volume ?? 100) / 100));
            $bgVol = max(0.05, min(1.0, ($stream->bg_music_volume ?? 30) / 100));

            $filterComplex = sprintf(
                '[0:v]%s[vout];[0:a]aformat=sample_rates=44100:channel_layouts=stereo,aresample=44100:async=1000:first_pts=0,volume=%.2f[a0];[1:a]aformat=sample_rates=44100:channel_layouts=stereo,aresample=44100:async=1000:first_pts=0,volume=%.2f[a1];[a0][a1]amix=inputs=2:duration=first:dropout_transition=2,aformat=sample_rates=44100:channel_layouts=stereo,aresample=44100,asetpts=N/SR/TB[aout]',
                $vf,
                $origVol,
                $bgVol
            );

            $filterAndMapArgs = sprintf(
                '-filter_complex %s -map "[vout]" -map "[aout]"',
                escapeshellarg($filterComplex)
            );
        } elseif ($audioMode === 'replace_bg_music' && $musicFileExists) {
            // Telif Koruması Modu: Orijinal Sesi Kapat, Yalnızca Telifsiz Fon Müziği Çal
            // asetpts=N/SR/TB ile döngü wrap-around anlarında zaman damgaları kesinlikle pürüzsüz artar.
            $bgVol = max(0.05, min(1.5, ($stream->bg_music_volume ?? 100) / 100));

            $filterComplex = sprintf(
                '[0:v]%s[vout];[1:a]aformat=sample_rates=44100:channel_layouts=stereo,aresample=44100:async=1:first_pts=0,volume=%.2f,asetpts=N/SR/TB[aout]',
                $vf,
                $bgVol
            );

            $filterAndMapArgs = sprintf(
                '-filter_complex %s -map "[vout]" -map "[aout]"',
                escapeshellarg($filterComplex)
            );
        } elseif ($audioMode === 'mute') {
            // Sessiz Mod: Boş AAC
            $filterComplex = sprintf('[0:v]%s[vout]', $vf);
            $filterAndMapArgs = sprintf(
                '-filter_complex %s -map "[vout]" -map 1:a:0',
                escapeshellarg($filterComplex)
            );
        } else {
            // Orijinal Ses Modu
            $filterComplex = sprintf(
                '[0:v]%s[vout];[0:a]aformat=sample_rates=44100:channel_layouts=stereo,aresample=44100:async=1000:first_pts=0,asetpts=N/SR/TB[aout]',
                $vf
            );
            $filterAndMapArgs = sprintf(
                '-filter_complex %s -map "[vout]" -map "[aout]"',
                escapeshellarg($filterComplex)
            );
        }

        // Çıkış (Tekil RTMP veya Eşzamanlı Çoklu Platform Tee Muxer)
        $endpointValues = array_values($endpoints);

        if (count($endpointValues) === 1) {
            $targetUrl = $endpointValues[0];
            // Meta (Instagram / Facebook) RTMPS bağlantılarında sunucu SSL el sıkışmasını (TLS fatal alert) önle
            $tlsFlag = str_starts_with($targetUrl, 'rtmps://') ? '-tls_verify 0 ' : '';

            // Instagram/Meta anahtarı URL sorgu parametreleri (?s_bl=1&...) içeriyorsa
            // FFmpeg'in RTMPS el sıkışmasını bozmaması için -rtmp_playpath ile güvenli ayrıştırma yapılır
            if (str_contains($targetUrl, '?') && preg_match('#^(rtmps?://[^/]+(?:/[^/]+)?)/(.+)$#', $targetUrl, $matches)) {
                $baseRtmp = $matches[1];
                $playpath = $matches[2];
                $outputArgs = sprintf(
                    '-max_muxing_queue_size 4096 -f flv -flvflags no_duration_filesize %s-rtmp_playpath %s %s',
                    $tlsFlag,
                    escapeshellarg($playpath),
                    escapeshellarg($baseRtmp)
                );
            } else {
                $outputArgs = sprintf(
                    '-max_muxing_queue_size 4096 -f flv -flvflags no_duration_filesize %s%s',
                    $tlsFlag,
                    escapeshellarg($targetUrl)
                );
            }
        } else {
            // Simulcast (Aynı anda YouTube, Instagram, TikTok)
            $teeTargets = array_map(function (string $url) {
                return '[f=flv:onfail=ignore]' . $url;
            }, $endpointValues);

            $teeString = implode('|', $teeTargets);

            $outputArgs = sprintf(
                '-flags +global_header -max_muxing_queue_size 4096 -f tee %s',
                escapeshellarg($teeString)
            );
        }

        return trim(sprintf(
            '%s -y %s %s %s %s %s',
            escapeshellcmd($this->ffmpegPath),
            $inputArgs,
            $filterAndMapArgs,
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

        // Linux VPS üzerinde eski yetim (orphan) runner veya ffmpeg süreçlerini tamamen sonlandır
        if (PHP_OS_FAMILY === 'Linux') {
            @shell_exec("pkill -9 -f 'runner_stream_{$stream->id}.sh' 2>/dev/null");
            @shell_exec("pkill -9 -f 'stream_{$stream->id}_playlist.txt' 2>/dev/null");
        }

        $stopFile = storage_path("app/live-streams/stream_{$stream->id}.stop");
        if (file_exists($stopFile)) {
            @unlink($stopFile);
        }

        $concatFile = $this->buildConcatFile($stream);
        $ffmpegCommand = $this->buildFfmpegCommand($stream, $concatFile);

        $logDir = storage_path('logs/live-streams');
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = "{$logDir}/stream_{$stream->id}.log";
        $headerLog = sprintf(
            "\n[%s] === SOCIALFLOW 7/24 CANLI YAYIN BAŞLATILIYOR (#%d - %s) ===\nKomut: %s\n------------------------------------------------------------\n",
            now()->toDateTimeString(),
            $stream->id,
            $stream->title,
            $ffmpegCommand
        );
        file_put_contents($logFile, $headerLog, FILE_APPEND);

        if (! $this->isFfmpegAvailable()) {
            $stream->update([
                'status' => 'error',
                'last_error' => 'Sunucuda FFmpeg kurulu değil veya shell_exec devre dışı. Lütfen sunucuda "apt-get install -y ffmpeg" komutunu çalıştırın.',
            ]);
            throw new \RuntimeException('Sunucuda FFmpeg bulunamadı. Canlı yayın akışı için sunucuda FFmpeg kurulu olmalıdır.');
        }

        $pid = $this->spawnBackgroundProcess($ffmpegCommand, $logFile, $stream);

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
        $stopFile = storage_path("app/live-streams/stream_{$stream->id}.stop");
        @file_put_contents($stopFile, 'stop');

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
     * Telifsiz arka plan fon müziği dosyasını döner veya sunucuda otomatik oluşturur.
     */
    public function resolveBackgroundMusicPath(LiveStream $stream): string
    {
        $dir = storage_path('app/audio-library');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // 1. Kullanıcı özel bir MP3/M4A/WAV yüklediyse
        if ($stream->bg_music_source === 'custom' && $stream->bg_music_path) {
            $customDisk = Storage::disk('public');
            if ($customDisk->exists($stream->bg_music_path)) {
                return $customDisk->path($stream->bg_music_path);
            }
            $abs = storage_path('app/public/' . $stream->bg_music_path);
            if (file_exists($abs)) {
                return $abs;
            }
        }

        // 2. Preset (Hazır Telifsiz Müzik)
        $preset = $stream->bg_music_preset ?: 'lofi_chill';
        $presetFile = "{$dir}/{$preset}.mp3";

        if (file_exists($presetFile) && filesize($presetFile) > 10000) {
            return $presetFile;
        }

        return $this->generatePresetAudioTrack($preset, $presetFile);
    }

    /**
     * FFmpeg matematiksel frekans sentezleyicisi (lavfi aevalsrc) ile
     * %100 Telifsiz (Content ID / Telif hakkı asla iddia edilemez) 60 saniyelik kusursuz döngü fon müziği üretir.
     */
    public function generatePresetAudioTrack(string $preset, ?string $targetPath = null): string
    {
        $dir = storage_path('app/audio-library');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $targetPath = $targetPath ?: "{$dir}/{$preset}.mp3";

        // Farklı müzik türleri için akor frekansları ve ses filtreleri:
        [$synthExpr, $afFilter] = match ($preset) {
            'ambient_relax' => [
                // 432 Hz frekansında huzurlu derin meditasyon & ortam pedi
                '0.14*sin(2*PI*216*t)*sin(2*PI*0.1*t) + 0.12*sin(2*PI*270*t)*sin(2*PI*0.15*t) + 0.10*sin(2*PI*324*t)*sin(2*PI*0.2*t) + 0.08*sin(2*PI*432*t)*sin(2*PI*0.08*t)',
                'lowpass=f=750,aecho=0.85:0.9:1200|2000:0.35|0.25,volume=1.2',
            ],
            'upbeat_energetic' => [
                // Enerjik modern ritmik vuruşlar ve bas
                '0.16*sin(2*PI*130.81*t)*sin(2*PI*2*t) + 0.14*sin(2*PI*261.63*t)*sin(2*PI*4*t) + 0.12*sin(2*PI*392*t)*sin(2*PI*1*t)',
                'highpass=f=80,lowpass=f=2800,aecho=0.8:0.8:500:0.2,volume=1.3',
            ],
            'acoustic_guitar' => [
                // Akustik melodi ve piyano rezonansı
                '0.14*sin(2*PI*246.94*t)*sin(2*PI*0.5*t) + 0.12*sin(2*PI*329.63*t)*sin(2*PI*0.33*t) + 0.10*sin(2*PI*493.88*t)*sin(2*PI*0.25*t)',
                'lowpass=f=1100,aecho=0.8:0.85:600|1100:0.3|0.2,volume=1.2',
            ],
            default => [
                // 'lofi_chill': Rahatlatıcı Lo-Fi Chill Synth Pad (Am7 Akoru: 220Hz, 261Hz, 329Hz, 392Hz)
                '0.14*sin(2*PI*220*t)*sin(2*PI*0.25*t) + 0.12*sin(2*PI*261.63*t)*sin(2*PI*0.125*t) + 0.12*sin(2*PI*329.63*t)*sin(2*PI*0.33*t) + 0.09*sin(2*PI*392*t)*sin(2*PI*0.2*t)',
                'lowpass=f=850,aecho=0.8:0.88:800|1500:0.35|0.22,volume=1.2',
            ],
        };

        if ($this->isFfmpegAvailable()) {
            $codec = str_ends_with(strtolower($targetPath), '.mp3') ? '-c:a libmp3lame -b:a 192k' : '-c:a aac -b:a 192k';
            $cmd = sprintf(
                '%s -nostdin -y -f lavfi -i %s -af %s -t 60 %s -ar 44100 -ac 2 %s 2>&1',
                escapeshellcmd($this->ffmpegPath),
                escapeshellarg("aevalsrc={$synthExpr}:s=44100"),
                escapeshellarg($afFilter),
                $codec,
                escapeshellarg($targetPath)
            );
            @shell_exec($cmd);
        }

        return $targetPath;
    }

    /**
     * İşletim sistemine (Linux / Windows) uygun şekilde arka plan işlemi başlatır ve PID döndürür.
     */
    protected function spawnBackgroundProcess(string $command, string $logFile, ?LiveStream $stream = null): ?int
    {
        $streamId = $stream ? $stream->id : 'default';
        $scriptDir = storage_path('app/live-streams');
        if (! is_dir($scriptDir)) {
            @mkdir($scriptDir, 0775, true);
        }

        $stopFile = "{$scriptDir}/stream_{$streamId}.stop";

        if (DIRECTORY_SEPARATOR === '\\') {
            $runnerFile = "{$scriptDir}/runner_stream_{$streamId}.bat";
            $stopFileWin = str_replace('/', '\\', $stopFile);
            $logFileWin = str_replace('/', '\\', $logFile);

            $batContent = "@echo off\r\n"
                . "set \"STOP_FILE={$stopFileWin}\"\r\n"
                . "set \"LOG_FILE={$logFileWin}\"\r\n"
                . "if exist \"%STOP_FILE%\" del \"%STOP_FILE%\"\r\n"
                . ":loop\r\n"
                . "if exist \"%STOP_FILE%\" goto end\r\n"
                . "echo [%date% %time%] [RUNNER] Canli yayin sureci baslatiliyor... >> \"%LOG_FILE%\"\r\n"
                . $command . " >> \"%LOG_FILE%\" 2>&1\r\n"
                . "if exist \"%STOP_FILE%\" goto end\r\n"
                . "echo [%date% %time%] [RUNNER] Yayin sonlandi, 2 saniye icinde yeniden baglaniliyor... >> \"%LOG_FILE%\"\r\n"
                . "timeout /t 2 /nobreak >nul\r\n"
                . "goto loop\r\n"
                . ":end\r\n";
            file_put_contents($runnerFile, $batContent);

            $wmicCmd = sprintf(
                'wmic process call create "cmd.exe /c \"\"%s\"\"" 2>&1',
                $runnerFile
            );
            $output = (string) @shell_exec($wmicCmd);
            if (preg_match('/ProcessId\s*=\s*(\d+)/', $output, $matches)) {
                return (int) $matches[1];
            }

            // Fallback: PowerShell Start-Process
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
        // Kendi kendini onaran (Self-Healing Loop) arka plan betiği:
        // Geçici ağ kopmasında veya platform bağlantı sıfırlamasında FFmpeg 2 saniye içinde otomatik yeniden başlar.
        $runnerFile = "{$scriptDir}/runner_stream_{$streamId}.sh";
        $shContent = "#!/bin/bash\n"
            . "STOP_FILE=" . escapeshellarg($stopFile) . "\n"
            . "LOG_FILE=" . escapeshellarg($logFile) . "\n"
            . "rm -f \"\$STOP_FILE\"\n\n"
            . "while [ ! -f \"\$STOP_FILE\" ]; do\n"
            . "    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] [RUNNER] FFmpeg canli yayin sureci baslatiliyor...\" >> \"\$LOG_FILE\"\n"
            . "    " . $command . " >> \"\$LOG_FILE\" 2>&1\n"
            . "    EXIT_CODE=\$?\n"
            . "    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] [RUNNER] FFmpeg sonlandi (kod: \$EXIT_CODE).\" >> \"\$LOG_FILE\"\n"
            . "    if [ -f \"\$STOP_FILE\" ]; then\n"
            . "        break\n"
            . "    fi\n"
            . "    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] [RUNNER] 2 saniye icinde otomatik yeniden baglaniliyor...\" >> \"\$LOG_FILE\"\n"
            . "    sleep 2\n"
            . "done\n"
            . "rm -f \"\$STOP_FILE\"\n";

        file_put_contents($runnerFile, $shContent);
        @chmod($runnerFile, 0755);

        $bgCmd = sprintf(
            'nohup bash %s </dev/null >> %s 2>&1 & echo $!',
            escapeshellarg($runnerFile),
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
