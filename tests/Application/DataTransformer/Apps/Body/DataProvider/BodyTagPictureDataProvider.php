<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body\DataProvider;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class BodyTagPictureDataProvider
{
    public function getData(): array
    {
        return [
            [
                'shots' => [
                    "1440w" => "https://images.ecestaticos.dev/B26-5pH9vylfOiapiBjXanvO7Ho=/615x99:827x381/1440x1920/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "1200w" => "https://images.ecestaticos.dev/gN2tLeVBCOcV5AKBmZeJhGYztTk=/615x99:827x381/1200x1600/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "996w" => "https://images.ecestaticos.dev/YRLxy6ChIKjekgdg_BN1DirWtJ8=/615x99:827x381/996x1328/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "640w" => "https://images.ecestaticos.dev/WByyZwZDIXdsAikGvHjMd3wOiUI=/615x99:827x381/560x747/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "390w" => "https://images.ecestaticos.dev/6LRdLT09KxKdAIaRQV6gbHtiZSQ=/615x99:827x381/390x520/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "568w" => "https://images.ecestaticos.dev/m70h5OCBdQyGjYRqai5qmRVZoUQ=/615x99:827x381/568x757/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "382w" => "https://images.ecestaticos.dev/ws_0oo3JORfvWxI_XKyluvDeGRI=/615x99:827x381/382x509/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "328w" => "https://images.ecestaticos.dev/YsYE5tLIS_WX3BU6agIfeikYUl8=/615x99:827x381/328x437/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg"
                ],
                'sizes' => [
                    '1440w' => ['width' => '1440', 'height' => '810'],
                    '1200w' => ['width' => '1200', 'height' => '675'],
                    '996w' => ['width' => '996', 'height' => '560'],
                    '640w' => ['width' => '640', 'height' => '360'],
                    '390w' => ['width' => '390', 'height' => '219'],
                    '568w' => ['width' => '568', 'height' => '320'],
                    '382w' => ['width' => '382', 'height' => '215'],
                    '328w' => ['width' => '328', 'height' => '185'],
                ],
                'photoFile' => '0a978399c4be84f3ce367624ca9589ad.jpg',
                'topX' => 0,
                'topY' => 0,
                'bottomX' => 320,
                'bottomY' => 180,
                'caption' => 'Sample Caption',
                'alternate' => 'Sample Alternate',
                'orientation' => 'landscape',
            ],
            [
                'shots' => [
                    "1440w" => "https://images.ecestaticos.dev/B26-5pH9vylfOiapiBjXanvO7Ho=/615x99:827x381/1440x1920/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "1200w" => "https://images.ecestaticos.dev/gN2tLeVBCOcV5AKBmZeJhGYztTk=/615x99:827x381/1200x1600/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "996w" => "https://images.ecestaticos.dev/YRLxy6ChIKjekgdg_BN1DirWtJ8=/615x99:827x381/996x1328/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "560w" => "https://images.ecestaticos.dev/WByyZwZDIXdsAikGvHjMd3wOiUI=/615x99:827x381/560x747/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "390w" => "https://images.ecestaticos.dev/6LRdLT09KxKdAIaRQV6gbHtiZSQ=/615x99:827x381/390x520/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "568w" => "https://images.ecestaticos.dev/m70h5OCBdQyGjYRqai5qmRVZoUQ=/615x99:827x381/568x757/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "382w" => "https://images.ecestaticos.dev/ws_0oo3JORfvWxI_XKyluvDeGRI=/615x99:827x381/382x509/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg",
                    "328w" => "https://images.ecestaticos.dev/YsYE5tLIS_WX3BU6agIfeikYUl8=/615x99:827x381/328x437/filters:fill(white):format(jpg)/dev.f.elconfidencial.com/original/0a9/783/99c/0a978399c4be84f3ce367624ca9589ad.jpg"
                ],
                'sizes' => [
                    '1440w' => ['width' => '1440', 'height' => '1920'],
                    '1200w' => ['width' => '1200', 'height' => '1600'],
                    '996w' => ['width' => '996', 'height' => '1328'],
                    '560w' => ['width' => '560', 'height' => '747'],
                    '390w' => ['width' => '390', 'height' => '520'],
                    '568w' => ['width' => '568', 'height' => '757'],
                    '382w' => ['width' => '382', 'height' => '509'],
                    '328w' => ['width' => '328', 'height' => '437'],
                ],
                'photoFile' => '0a978399c4be84f3ce367624ca9589ad.jpg',
                'topX' => 615,
                'topY' => 99,
                'bottomX' => 827,
                'bottomY' => 381,
                'caption' => 'Sample Caption',
                'alternate' => 'Sample Alternate',
                'orientation' => 'landscape',
            ],


        ];
    }
}
