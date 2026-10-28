<?php

namespace App\Enums;

enum SocialPlatformCode: string
{
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';
    case YOUTUBE = 'youtube';
    case X = 'x';
    case PINTEREST = 'pinterest';
    case TIKTOK = 'tiktok';
    case LINKEDIN = 'linkedin';
    case WHATSAPP = 'whatsapp';
    case TELEGRAM = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::FACEBOOK => 'Facebook',
            self::INSTAGRAM => 'Instagram',
            self::YOUTUBE => 'YouTube',
            self::X => 'X (Twitter)',
            self::PINTEREST => 'Pinterest',
            self::TIKTOK => 'TikTok',
            self::LINKEDIN => 'LinkedIn',
            self::WHATSAPP => 'WhatsApp',
            self::TELEGRAM => 'Telegram',
        };
    }

    public function maxCaptionLength(): int
    {
        return match ($this) {
            self::FACEBOOK => 63206,
            self::INSTAGRAM => 2200,
            self::YOUTUBE => 5000,
            self::X => 280,
            self::PINTEREST => 500,
            self::TIKTOK => 2200,
            self::LINKEDIN => 3000,
            self::WHATSAPP => 4096,
            self::TELEGRAM => 4096,
        };
    }

    /**
     * Her platformun izin verdiği en uygun ve algoritmik maksimum hashtag sayısı (5 marka + 5 trend = 10)
     */
    public function maxHashtags(): int
    {
        return 10;
    }
}
