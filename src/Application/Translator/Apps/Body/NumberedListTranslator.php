<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\Translator\Apps\Body;

use Ec\Editorial\Domain\Model\Body\ListItem;
use Ec\Editorial\Domain\Model\Body\NumberedList;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\StrategyTranslator;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\Translator;

/**
 * @author Antonio Jose Cerezo Aranda <acerezo@elconfidencial.com>
 */
class NumberedListTranslator implements Translator
{
    public function translate(StrategyTranslator $strategy, $source, $destiny): array
    {
        if (!$source instanceof NumberedList) {
            throw new \InvalidArgumentException(get_class($source).' is not instance of '.NumberedList::class);
        }

        $orderedList = ['type' => NumberedList::TYPE];
        $orderedList['item'] = [];

        /** @var ListItem $listItem */
        foreach ($source as $listItem) {
            $orderedList['item'][] = $strategy->execute($listItem);
        }

        return $orderedList;
    }

    public function canTranslate(): string
    {
        return NumberedList::class;
    }

    public function canTranslateTo(): string
    {
        // TODO: Implement canTranslateTo() method.
    }
}
