<?php

namespace App\Application\DataTransformer\Apps;


use Ec\Editorial\Domain\Model\Editorial;
use Ec\Journalist\Domain\Model\Journalists;
use Ec\Section\Domain\Model\Section;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
interface AppsDatatransformer
{

    public function write(Editorial $editorial,Journalists $journalists, Section $section): AppsDatatransformer;

    public function read(): array;
}
