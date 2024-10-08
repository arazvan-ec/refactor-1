<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Application\Translator\Apps\Body\Traits;

use Ec\Editorial\Domain\Model\Body\ElementContentWithLinks;
use Ec\Editorial\Domain\Model\Body\Link;

/**
 * @author Antonio Jose Cerezo Aranda <acerezo@elconfidencial.com>
 */
trait ContentWithLinksTranslate
{
    private function translateContentWithLink(ElementContentWithLinks $contentWithLinks): array
    {
        $contentContract = [];

        $content = $contentWithLinks->content();
        $offset = 0;
        /**
         * @var Link $link
         */
        foreach ($contentWithLinks->links() as $replace => $link) {
            $positionReplace = strpos($content, $replace);
            $linkPosition = $positionReplace - $offset;
            $contentContract[] = ['content' => substr($content, $offset, $linkPosition)];

            $linkArray = [];
            $linkArray['type'] = Link::TYPE;
            $linkArray['href'] = $link->url();
            $linkArray['target'] = $link->target();
            $linkArray['children'][0]['content'] = $link->content();

            $contentContract[] = $linkArray;

            $offset = $positionReplace + strlen($replace);
        }

        if (empty($content) || ($offset < strlen($content))) {
            $contentContract[] = ['content' => substr($content, $offset)];
        }

        return $contentContract;
    }
}
