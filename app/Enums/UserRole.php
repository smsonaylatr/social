<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Süper Yönetici',
            self::ADMIN => 'Yönetici',
            self::EDITOR => 'Editör',
            self::VIEWER => 'İzleyici',
        };
    }
}
