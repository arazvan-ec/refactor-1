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
        $links = [];

        /**
         * @var Link $link
         */
        foreach ($contentWithLinks->links() as $replace => $link) {
            $links[$replace] = [
                'type' => Link::TYPE,
                'content' => $link->content(),
                'url' => $link->url(),
                'target' => $link->target(),
            ];
        }

        return $links;
    }
}
