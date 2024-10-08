<?php

namespace App\Application\Translator\Apps\Body;

use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\StrategyTranslator;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\Translator;
use Ec\Infrastructure\Persistence\Doctrine\Traits\ClassNameTrait;

/**
 * @author Fernando Guerrero Cabrera <fguerrero@ext.elconfidencial.com>
 */
class TranslatorStrategy implements StrategyTranslator
{
    use ClassNameTrait;

    /** @var Translator[] */
    private array $translators = [];

    public function addTranslator(Translator $domainTranslator): StrategyTranslator
    {
        $this->translators[$domainTranslator->canTranslate()] = $domainTranslator;

        return $this;
    }

    public function execute($source, $destiny = null)
    {
        $type = is_array($source) ? $this->getTypeInArray($source) : $this->getClassName($source);

        if (empty($this->translators[$type])) {
            throw new \InvalidArgumentException($type.' has not translator.');
        }

        return $this->translators[$type]->translate($this, $source, $destiny);
    }

    private function getTypeInArray(array $source): string
    {
        return $source['type'] ?? Body::BODY_NORMAL;
    }
}
