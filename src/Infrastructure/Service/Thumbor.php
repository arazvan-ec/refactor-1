<?php

namespace App\Infrastructure\Service;


use Thumbor\Url\BuilderFactory;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class Thumbor
{

    private string $awsBucket;
    private BuilderFactory $thumborFactory;

    public function __construct(string $thumborServerUrl, string $thumborSecret, string $awsBucket)
    {
        $this->awsBucket = $awsBucket;
        $this->thumborFactory = BuilderFactory::construct($thumborServerUrl, $thumborSecret);
    }


    public function createJournalistImage(string $fileImage): string
    {
        $path1 = \substr($fileImage, 0, 3);
        $path2 = \substr($fileImage, 3, 3);
        $path3 = \substr($fileImage, 6, 3);

        $path=  $this->awsBucket."/journalist/{$path1}/{$path2}/{$path3}/{$fileImage}";

        return $this->thumborFactory->url($path);
    }

}
