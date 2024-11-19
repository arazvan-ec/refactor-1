<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps\Body;

use Assert\Assertion;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
class BodyTagInsertedNewsDataTransformer extends ElementTypeDataTransformer
{
    public function canTransform(): string
    {
        return BodyTagInsertedNews::class;
    }

    public function read(): array
    {
        $message = 'BodyElement should be instance of '.BodyTagInsertedNews::class;
        /** @var BodyTagInsertedNews $bodyElement */
        $bodyElement = $this->bodyElement;
        Assertion::isInstanceOf($bodyElement, BodyTagInsertedNews::class, $message);

        $elementArray = parent::read();


        $insertedNews = $this->resolveData()['insertedNews'][$bodyElement->editorialId()->id()];

        $elementArray['editorialId'] = $insertedNews['editorialId'];
        $elementArray['title'] = $insertedNews['title'];
        $elementArray['signatures'] = $this->retrieveJournalists($insertedNews['signatures'], $this->resolveData()['signatures']);
        $elementArray['editorial'] = $insertedNews['editorial'];
        $elementArray['photo'] = $insertedNews['photo'];

        return $elementArray;
    }

    private function retrieveJournalists(array $journalistsInserted, array $journalists): array
    {
        $result = [];

        foreach ($journalistsInserted as $signature) {
            $result[] = $journalists[$signature];
        }

        return $result;
    }
}
