<?php

namespace App\Application\Translator\Apps\Body;

use Ec\Editorial\Domain\Model\Body\Live\BodyLive;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\StrategyTranslator;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\Translator;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class BodyLiveTranslator implements Translator
{
    public function translate(StrategyTranslator $strategy, $source, $destiny): array
    {
        if (!$source instanceof BodyLive) {
            throw new \InvalidArgumentException(get_class($source).' is not a '.BodyLive::class);
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
        return BodyLive::class;
    }

    public function canTranslateTo(): string
    {
        return 'array';
    }
}
