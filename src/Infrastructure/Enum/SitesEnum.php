<?php

namespace App\Infrastructure\Enum;

enum SitesEnum: string
{
    case ELCONFIDENCIAL = '1';
    case VANITATIS = '2';
    case ALIMENTE = '5';

    public static function getHostnameById(string $id): string
    {
        return match ($id) {
            self::ELCONFIDENCIAL->value => 'elconfidencial',
            self::VANITATIS->value => 'vanitatis.elconfidencial',
            self::ALIMENTE->value => 'alimente.elconfidencial',
            default => 'elconfidencial',
        };
    }
}
