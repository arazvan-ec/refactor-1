<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use Ec\Multimedia\Domain\Model\Multimedia;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
interface MultimediaDataTransformer
{
    public function write(Multimedia $multimedia): MultimediaDataTransformer;

    /**
     * @return array<string, mixed>
     */
    public function read(): array;
}
