<?php

namespace App\Application\DataTransformer\Apps;

use Ec\Editorial\Domain\Model\Editorial;
use Ec\Section\Domain\Model\Section;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
interface AppsDataTransformer
{
    public function write(Editorial $editorial, array $journalists, Section $section): AppsDataTransformer;

    /**
     * @return array<string, mixed>
     */
    public function read(): array;
}
