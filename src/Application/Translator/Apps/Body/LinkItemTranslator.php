<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\Translator\Apps\Body;

use App\Application\Translator\Apps\Body\Traits\ContentWithLinksTranslate;
use Ec\Editorial\Domain\Model\Body\ListItem;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\StrategyTranslator;
use Ec\Editorial\Infrastructure\Persistence\Doctrine\Translator\DomainToEntity\Translator;

/**
 * @author Antonio Jose Cerezo Aranda <acerezo@elconfidencial.com>
 */
class LinkItemTranslator implements Translator
{
    use ContentWithLinksTranslate;

    public function translate(StrategyTranslator $strategy, $source, $destiny): array
    {
        if (!$source instanceof ListItem) {
            throw new \InvalidArgumentException(get_class($source).' is not instance of '.ListItem::class);
        }

        return [
            'type' => ListItem::TYPE,
            'content' => $source->content(),
            'links' => $this->translateContentWithLink($source)
        ];
    }

    public function canTranslate(): string
    {
        return ListItem::class;
    }

    public function canTranslateTo(): string
    {
        // TODO: Implement canTranslateTo() method.
    }
}
