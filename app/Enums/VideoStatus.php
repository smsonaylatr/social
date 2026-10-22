<?php

namespace App\Enums;

enum VideoStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Beklemede',
            self::PROCESSING => 'İşleniyor',
            self::READY => 'Yayına Hazır',
            self::FAILED => 'İşleme Başarısız',
            self::ARCHIVED => 'Arşivlendi',
        };
    }
}
