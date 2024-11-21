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

    public function read(): array;
}
