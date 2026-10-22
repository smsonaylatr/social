<?php

namespace App\Enums;

enum PlaylistLoopMode: string
{
    case SEQUENTIAL = 'sequential';
    case SHUFFLE = 'shuffle';
    case PRIORITY = 'priority';

    public function label(): string
    {
        return match ($this) {
            self::SEQUENTIAL => 'Sıralı Yayınla',
            self::SHUFFLE => 'Karışık Sırayla Yayınla',
            self::PRIORITY => 'Öncelik Puanına Göre',
        };
    }
}
