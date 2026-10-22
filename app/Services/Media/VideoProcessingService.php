<?php

namespace App\Services\Media;

use App\Models\Setting;
use App\Models\Video;
use App\Services\Media\DTOs\VideoMetadataDTO;
use getID3;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoProcessingService
{
    protected string $disk;
    protected string $ffmpegPath;
    protected string $ffprobePath;

    public function __construct(string $disk = 'public')
    {
        $this->disk = $disk;
        $this->ffmpegPath = Setting::get('ffmpeg_binary_path', 'ffmpeg');
        $this->ffprobePath = Setting::get('ffprobe_binary_path', 'ffprobe');
    }

    /**
     * Fast pure-PHP MP4 metadata extractor (zero dependencies, works on all PHP environments)
     */
    public static function parseMp4Quick(string $filePath): array
    {
        $result = [
            'duration' => 0,
            'width' => 1080,
            'height' => 1920,
            'rotation' => 0,
        ];

        if (! file_exists($filePath) || filesize($filePath) < 16) {
            return $result;
        }

        $fp = @fopen($filePath, 'rb');
        if (! $fp) {
            return $result;
        }

        $fileSize = filesize($filePath);
        $offset = 0;
        $maxScan = min($fileSize, 25 * 1024 * 1024); // Scan up to 25MB for moov box

        while ($offset < $maxScan && ! feof($fp)) {
            fseek($fp, $offset);
            $header = fread($fp, 8);
            if (strlen($header) < 8) {
                break;
            }

            $boxSize = unpack('N', substr($header, 0, 4))[1];
            $boxType = substr($header, 4, 4);

            if ($boxSize === 1) { // 64-bit size
                $largeSizeData = fread($fp, 8);
                if (strlen($largeSizeData) < 8) break;
                $upper = unpack('N', substr($largeSizeData, 0, 4))[1];
                $lower = unpack('N', substr($largeSizeData, 4, 4))[1];
                $boxSize = ($upper << 32) | $lower;
                $headerSize = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $fileSize - $offset;
                $headerSize = 8;
            } else {
                $headerSize = 8;
            }

            if ($boxSize < 8) {
                break;
            }

            if ($boxType === 'moov') {
                // Read moov contents (up to 15MB)
                $moovLen = min($boxSize - $headerSize, 15 * 1024 * 1024);
                $moovData = fread($fp, $moovLen);
                self::parseMoovData($moovData, $result);
                break;
            }

            $offset += $boxSize;
        }

        fclose($fp);
        return $result;
    }

    protected static function parseMoovData(string $data, array &$result): void
    {
        $len = strlen($data);
        $pos = 0;

        while ($pos + 8 <= $len) {
            $boxSize = unpack('N', substr($data, $pos, 4))[1];
            $boxType = substr($data, $pos + 4, 4);

            if ($boxSize < 8 || $pos + $boxSize > $len) {
                $boxSize = max(8, $len - $pos);
            }

            if ($boxType === 'mvhd') {
                $version = ord($data[$pos + 8]);
                if ($version === 1 && $pos + 36 <= $len) {
                    $timescale = unpack('N', substr($data, $pos + 28, 4))[1];
                    $durationUpper = unpack('N', substr($data, $pos + 32, 4))[1];
                    $durationLower = unpack('N', substr($data, $pos + 36, 4))[1];
                    $duration = ($durationUpper << 32) | $durationLower;
                    if ($timescale > 0) {
                        $result['duration'] = (int) round($duration / $timescale);
                    }
                } elseif ($version === 0 && $pos + 28 <= $len) {
                    $timescale = unpack('N', substr($data, $pos + 20, 4))[1];
                    $duration = unpack('N', substr($data, $pos + 24, 4))[1];
                    if ($timescale > 0) {
                        $result['duration'] = (int) round($duration / $timescale);
                    }
                }
            } elseif ($boxType === 'trak') {
                $trakData = substr($data, $pos + 8, $boxSize - 8);
                self::parseTrakData($trakData, $result);
            }

            $pos += $boxSize;
        }
    }

    protected static function parseTrakData(string $data, array &$result): void
    {
        $len = strlen($data);
        $pos = 0;

        while ($pos + 8 <= $len) {
            $boxSize = unpack('N', substr($data, $pos, 4))[1];
            $boxType = substr($data, $pos + 4, 4);

            if ($boxSize < 8 || $pos + $boxSize > $len) {
                break;
            }

            if ($boxType === 'tkhd') {
                $version = ord($data[$pos + 8]);
                $headerOffset = $version === 1 ? $pos + 8 + 4 + 16 + 4 + 4 + 8 + 8 + 2 + 2 + 2 + 2 : $pos + 8 + 4 + 8 + 4 + 4 + 4 + 8 + 2 + 2 + 2 + 2;

                if ($headerOffset + 36 + 8 <= $len) {
                    // Matrix: 9 x 4 bytes
                    $matrix = substr($data, $headerOffset, 36);
                    $a = unpack('N', substr($matrix, 0, 4))[1];
                    $b = unpack('N', substr($matrix, 4, 4))[1];
                    $c = unpack('N', substr($matrix, 12, 4))[1];
                    $d = unpack('N', substr($matrix, 16, 4))[1];

                    // Check rotation from matrix
                    $rotation = 0;
                    if ($b === 0x00010000 && $c === 0xFFFF0000) {
                        $rotation = 90;
                    } elseif ($a === 0xFFFF0000 && $d === 0xFFFF0000) {
                        $rotation = 180;
                    } elseif ($b === 0xFFFF0000 && $c === 0x00010000) {
                        $rotation = 270;
                    }

                    $rawW = unpack('N', substr($data, $headerOffset + 36, 4))[1] >> 16;
                    $rawH = unpack('N', substr($data, $headerOffset + 40, 4))[1] >> 16;

                    if ($rawW > 0 && $rawH > 0) {
                        if ($rotation === 90 || $rotation === 270) {
                            $result['width'] = $rawH;
                            $result['height'] = $rawW;
                        } else {
                            $result['width'] = $rawW;
                            $result['height'] = $rawH;
                        }
                        $result['rotation'] = $rotation;
                    }
                }
            }

            $pos += $boxSize;
        }
    }

    /**
     * Check if a video with the same file hash already exists
     */
    public function findDuplicate(string $fileHash, ?int $ignoreVideoId = null): ?Video
    {
        $query = Video::where('file_hash', $fileHash);

        if ($ignoreVideoId) {
            $query->where('id', '!=', $ignoreVideoId);
        }

        return $query->first();
    }

    /**
     * Analyze uploaded video file and extract technical metadata
     */
    public function analyzeVideo(string $relativeStoragePath): VideoMetadataDTO
    {
        $absolutePath = Storage::disk($this->disk)->path($relativeStoragePath);

        if (! file_exists($absolutePath)) {
            throw new \InvalidArgumentException("Video dosyası bulunamadı: {$absolutePath}");
        }

        $fileHash = hash_file('sha256', $absolutePath);
        $fileSize = filesize($absolutePath);

        // Fast pure-PHP MP4 metadata extractor
        $quickInfo = self::parseMp4Quick($absolutePath);
        $durationSeconds = $quickInfo['duration'];
        $width = $quickInfo['width'];
        $height = $quickInfo['height'];
        $fps = 30.0;
        $mimeType = (function_exists('mime_content_type') ? @mime_content_type($absolutePath) : null) ?: 'video/mp4';
        $rawMetadata = [
            'width' => $width,
            'height' => $height,
            'duration' => $durationSeconds,
            'rotation' => $quickInfo['rotation'] ?? 0,
        ];

        // If getID3 exists, attempt supplementary analysis
        if (class_exists('getID3')) {
            try {
                $getID3 = new getID3();
                $fileInfo = $getID3->analyze($absolutePath);

                if ($durationSeconds <= 0 && ! empty($fileInfo['playtime_seconds'])) {
                    $durationSeconds = (int) round($fileInfo['playtime_seconds']);
                }
                if ($width <= 0 && ! empty($fileInfo['video']['resolution_x'])) {
                    $width = (int) $fileInfo['video']['resolution_x'];
                }
                if ($height <= 0 && ! empty($fileInfo['video']['resolution_y'])) {
                    $height = (int) $fileInfo['video']['resolution_y'];
                }
                if (! empty($fileInfo['video']['frame_rate'])) {
                    $fps = (float) $fileInfo['video']['frame_rate'];
                }
                if (! empty($fileInfo['mime_type'])) {
                    $mimeType = $fileInfo['mime_type'];
                }
            } catch (\Throwable $e) {
                // supplementary check failed, proceed with pure PHP metadata
            }
        }

        if ($width <= 0) $width = 1080;
        if ($height <= 0) $height = 1920;

        // Calculate aspect ratio
        $aspectRatio = $this->calculateAspectRatio($width, $height);

        // Generate thumbnail safely without interrupting metadata flow
        $thumbnailPath = null;
        try {
            $thumbnailPath = $this->generateThumbnail($relativeStoragePath, $durationSeconds);
        } catch (\Throwable $e) {
            Log::warning("Thumbnail oluşturma hatası ({$relativeStoragePath}): " . $e->getMessage());
        }

        return new VideoMetadataDTO(
            fileHash: $fileHash,
            fileSize: $fileSize,
            durationSeconds: $durationSeconds,
            width: $width,
            height: $height,
            aspectRatio: $aspectRatio,
            fps: $fps,
            mimeType: $mimeType,
            thumbnailPath: $thumbnailPath,
            rawMetadata: $rawMetadata
        );
    }

    /**
     * Generate thumbnail from video at 1st second (or mid duration)
     */
    public function generateThumbnail(string $relativeVideoPath, int $durationSeconds = 0): ?string
    {
        $absoluteVideoPath = Storage::disk($this->disk)->path($relativeVideoPath);
        $thumbnailRelativePath = 'thumbnails/' . pathinfo($relativeVideoPath, PATHINFO_FILENAME) . '.jpg';
        $absoluteThumbnailPath = Storage::disk($this->disk)->path($thumbnailRelativePath);

        $thumbnailDir = dirname($absoluteThumbnailPath);
        if (! is_dir($thumbnailDir)) {
            @mkdir($thumbnailDir, 0775, true);
        }

        $seekSecond = min(1, max(0, (int) floor($durationSeconds / 2)));

        // Try FFmpeg if shell_exec is allowed
        $disabledFunctions = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (function_exists('shell_exec') && ! in_array('shell_exec', $disabledFunctions, true)) {
            $ffmpegCmd = sprintf(
                '%s -y -ss %d -i %s -vframes 1 -q:v 2 %s 2>&1',
                escapeshellcmd($this->ffmpegPath),
                $seekSecond,
                escapeshellarg($absoluteVideoPath),
                escapeshellarg($absoluteThumbnailPath)
            );

            @shell_exec($ffmpegCmd);

            if (file_exists($absoluteThumbnailPath) && filesize($absoluteThumbnailPath) > 0) {
                return $thumbnailRelativePath;
            }
        }

        // Return null if server FFmpeg is not available.
        // Frontend video player and client-side canvas capture will seamlessly render
        // the actual video frame and upload the crystal-clear thumbnail.
        return null;
    }

    protected function generateFallbackThumbnail(string $thumbnailRelativePath, string $title): ?string
    {
        $absoluteThumbnailPath = Storage::disk($this->disk)->path($thumbnailRelativePath);

        // Generate GD image placeholder with brand styling if GD is available
        if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
            try {
                $img = imagecreatetruecolor(640, 360);
                $bg = imagecolorallocate($img, 30, 27, 75); // Dark indigo #1e1b4b
                $textColor = imagecolorallocate($img, 243, 244, 246);
                $accentColor = imagecolorallocate($img, 99, 102, 241);

                imagefill($img, 0, 0, $bg);
                imagefilledrectangle($img, 20, 20, 620, 340, $accentColor);
                imagefilledrectangle($img, 24, 24, 616, 336, $bg);

                $displayText = mb_substr($title, 0, 30);
                imagestring($img, 5, 40, 160, 'SocialFlow AI - Video', $textColor);
                imagestring($img, 3, 40, 190, $displayText, $textColor);

                imagejpeg($img, $absoluteThumbnailPath, 85);
                imagedestroy($img);

                if (file_exists($absoluteThumbnailPath) && filesize($absoluteThumbnailPath) > 0) {
                    return $thumbnailRelativePath;
                }
            } catch (\Throwable $e) {
                // fall through to placeholder copy
            }
        }

        // Fallback 2: Copy pre-built video placeholder PNG if available
        $placeholderSource = public_path('images/video-placeholder.png');
        if (file_exists($placeholderSource)) {
            @copy($placeholderSource, $absoluteThumbnailPath);
            if (file_exists($absoluteThumbnailPath) && filesize($absoluteThumbnailPath) > 0) {
                return $thumbnailRelativePath;
            }
        }

        // Return null if thumbnail file could not be created on disk
        return null;
    }

    /**
     * Compute aspect ratio string (e.g. 9:16, 16:9, 1:1, 4:5)
     */
    public function calculateAspectRatio(int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return '9:16';
        }

        $ratio = $width / $height;

        if (abs($ratio - (9 / 16)) < 0.08) {
            return '9:16';
        }
        if (abs($ratio - (16 / 9)) < 0.08) {
            return '16:9';
        }
        if (abs($ratio - 1.0) < 0.08) {
            return '1:1';
        }
        if (abs($ratio - (4 / 5)) < 0.08) {
            return '4:5';
        }

        $gcd = $this->gcd($width, $height);
        return ($width / $gcd) . ':' . ($height / $gcd);
    }

    protected function gcd(int $a, int $b): int
    {
        return $b ? $this->gcd($b, $a % $b) : $a;
    }

    /**
     * Validate video compatibility for a specific social platform
     */
    public function validatePlatformCompatibility(Video $video, string $platformCode): array
    {
        $errors = [];
        $warnings = [];

        // Check if platform is explicitly restricted for this video
        if (is_array($video->restricted_platforms) && in_array($platformCode, $video->restricted_platforms)) {
            $errors[] = 'Bu video için ' . strtoupper($platformCode) . ' platformunda yayınlama kısıtlaması tanımlanmış.';
            return ['compatible' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        switch ($platformCode) {
            case 'instagram':
                // Reels: max 90 seconds, recommended 9:16, max 100MB
                if ($video->duration_seconds > 90) {
                    $warnings[] = 'Instagram Reels için önerilen azami süre 90 saniyedir (Mevcut: ' . $video->duration_seconds . 's).';
                }
                if ($video->aspect_ratio !== '9:16') {
                    $warnings[] = 'Instagram Reels için 9:16 dikey format önerilir (Mevcut: ' . $video->aspect_ratio . ').';
                }
                break;

            case 'tiktok':
                // TikTok: max 10 minutes, recommended 9:16
                if ($video->duration_seconds > 600) {
                    $errors[] = 'TikTok için video süresi en fazla 10 dakika olabilir.';
                }
                if ($video->aspect_ratio !== '9:16') {
                    $warnings[] = 'TikTok akışı için 9:16 dikey video önerilir.';
                }
                break;

            case 'youtube':
                // Shorts: <= 60 seconds and vertical 9:16 or square
                if ($video->duration_seconds > 60 && $video->aspect_ratio === '9:16') {
                    $warnings[] = 'YouTube Shorts olarak kabul edilmesi için video süresi 60 saniye veya altında olmalıdır.';
                }
                break;

            case 'x':
                // X (Twitter): max 140s for standard users
                if ($video->duration_seconds > 140) {
                    $warnings[] = 'Standart X hesaplarında azami video süresi 2 dakika 20 saniyedir.';
                }
                break;

            case 'pinterest':
                // Video pin: max 15 minutes, recommended 9:16 or 2:3
                if ($video->duration_seconds > 900) {
                    $errors[] = 'Pinterest video pinleri için azami süre 15 dakikadır.';
                }
                break;
        }

        return [
            'compatible' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
