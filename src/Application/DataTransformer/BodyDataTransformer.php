<?php

namespace App\Application\DataTransformer;

use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyElement;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class BodyDataTransformer
{
    public function __construct(
        private readonly BodyElementDataTransformerHandler $bodyElementDataTransformerHandler,
    ) {
    }

    public function execute(Body $body, array $resolveData, array $membershipLinkCombine): array
    {
        $parsedBody = [
            'type' => $body->type(),
            'elements' => [],
        ];

        /** @var BodyElement $bodyElement */
        foreach ($body as $bodyElement) {
            $parsedBody['elements'][] = $this->bodyElementDataTransformerHandler->execute($bodyElement,$resolveData, $membershipLinkCombine);
        }

        return $parsedBody;
    }
}
