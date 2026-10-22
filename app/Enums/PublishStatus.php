<?php

namespace App\Enums;

enum PublishStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PENDING_APPROVAL = 'pending_approval';
    case QUEUED = 'queued';
    case UPLOADING = 'uploading';
    case PROCESSING = 'processing';
    case PUBLISHED = 'published';
    case PARTIALLY_PUBLISHED = 'partially_published';
    case FAILED = 'failed';
    case RETRYING = 'retrying';
    case BLOCKED_POLICY = 'blocked_policy';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Taslak',
            self::SCHEDULED => 'Planlandı',
            self::PENDING_APPROVAL => 'Onay Bekliyor',
            self::QUEUED => 'Kuyrukta',
            self::UPLOADING => 'Yükleniyor',
            self::PROCESSING => 'İşleniyor',
            self::PUBLISHED => 'Yayınlandı',
            self::PARTIALLY_PUBLISHED => 'Kısmen Başarılı',
            self::FAILED => 'Başarısız',
            self::RETRYING => 'Tekrar Denenecek',
            self::BLOCKED_POLICY => 'Politika Nedeniyle Engellendi',
            self::CANCELLED => 'İptal Edildi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SCHEDULED => 'info',
            self::PENDING_APPROVAL => 'warning',
            self::QUEUED => 'primary',
            self::UPLOADING, self::PROCESSING, self::RETRYING => 'warning',
            self::PUBLISHED => 'success',
            self::PARTIALLY_PUBLISHED => 'warning',
            self::FAILED, self::BLOCKED_POLICY => 'danger',
            self::CANCELLED => 'gray',
        };
    }
}
