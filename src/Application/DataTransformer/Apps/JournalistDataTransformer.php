<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use Ec\Editorial\Domain\Model\Signatures;
use Ec\Section\Domain\Model\Section;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
interface JournalistDataTransformer
{
    public function write(Signatures $signatures, Section $section): JournalistDataTransformer;

    public function read(): array;
}
