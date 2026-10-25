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
     * Her platformun izin verdiği en yüksek / teknik maksimum hashtag sayısı
     */
    public function maxHashtags(): int
    {
        return match ($this) {
            self::INSTAGRAM => 30, // Resmi Instagram sınırı tam 30
            self::FACEBOOK => 20,  // Facebook
            self::PINTEREST => 20, // Pinterest en fazla 20
            self::TELEGRAM => 20,  // Telegram
            self::TIKTOK => 15,    // TikTok önerilen ve güvenli üst sınır
            self::YOUTUBE => 15,   // YouTube kuralı: 15'ten fazlası girilirse tüm hashtagler yok sayılır
            self::LINKEDIN => 15,  // LinkedIn
            self::WHATSAPP => 15,  // WhatsApp
            self::X => 2,          // X (Twitter) 280 karakter sınırı nedeniyle 2 (en fazla 2 önerilir)
        };
    }
}
