<?php

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use Ec\Editorial\Domain\Model\Multimedia\Multimedia as MultimediaEditorial;
use Ec\Editorial\Domain\Model\Opening;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
interface MultimediaMediaDataTransformer
{
    /**
     * @param array<mixed> $arrayMultimedia
     */
    public function write(array $arrayMultimedia, Opening $openingMultimedia): MultimediaMediaDataTransformer;

    /**
     * @return array<string, mixed>
     */
    public function read(): array;
}
