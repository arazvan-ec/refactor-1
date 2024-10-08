<?php

namespace App\Application\Translator\Apps\Body;

use Ec\Editorial\Domain\Model\Body\BodyNormal;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\StrategyTranslator;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\Translator;

/**
 * @author Fernando Guerrero Cabrera <fguerrero@ext.elconfidencial.com>
 */
class BodyNormalTranslator implements Translator
{
    public function translate(StrategyTranslator $strategy, $source, $destiny): array
    {
        if (!$source instanceof BodyNormal) {
            throw new \InvalidArgumentException(get_class($source).' is not a '.BodyNormal::class);
        }

        $bodyArray = [];
        foreach ($source as $bodyElement) {
            $bodyElementArray = $strategy->execute($bodyElement);
            $bodyArray[] = $bodyElementArray;
        }

        return $bodyArray;
    }

    public function canTranslate(): string
    {
        return BodyNormal::class;
    }

    public function canTranslateTo(): string
    {
        return 'array';
    }
}
