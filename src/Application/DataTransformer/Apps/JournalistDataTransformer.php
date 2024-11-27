<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use Ec\Journalist\Domain\Model\Journalist;
use Ec\Section\Domain\Model\Section;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
interface JournalistDataTransformer
{
    /**
     * @param Journalist[] $journalists
     *
     * @return $this
     */
    public function write(array $journalists, Section $section): JournalistDataTransformer;

    /**
     * @return Journalist[]
     */
    public function read(): array;
}
