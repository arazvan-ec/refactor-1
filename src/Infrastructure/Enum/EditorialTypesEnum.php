<?php

namespace App\Infrastructure\Enum;

enum EditorialTypesEnum: string
{
    case NEWS = 'news';


    case BLOG = 'blog';


    case LIVESPORT = 'livesport';

    /**
     * Constante para editorial de live.
     */
    case LIVE = 'live';

    /**
     * Constante para editorial de crónica.
     */
    case CHRONICLE = 'chronicle';

    /**
     * Constante para editorial de lovers.
     */
    case LOVERS = 'lovers';

    public static function getNameById(string $id): array
    {

        return match ($id) {
            self::NEWS->value => ['id' => '1', 'name' => 'noticia'],
            self::BLOG->value => ['id' => '3', 'name' => 'blog'],
            self::LIVESPORT->value => ['id' => '12', 'name' => 'directo deportivo'],
            self::LIVE->value => ['id' => '13', 'name' => 'directo informativo'],
            self::CHRONICLE->value => ['id' => '14', 'name' => 'chronicle'],
            self::LOVERS->value => ['id' => '15', 'name' => 'lovers'],
            default => ['id' => '1', 'name' => 'noticia'],
        };
    }
}
