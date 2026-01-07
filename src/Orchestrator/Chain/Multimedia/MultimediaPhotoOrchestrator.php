<?php

/**
 * @copyright
 */

namespace App\Orchestrator\Chain\Multimedia;

use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class MultimediaPhotoOrchestrator implements MultimediaOrchestratorInterface
{
    public function __construct(
        private readonly QueryMultimediaClient $queryMultimediaClient,
    ) {
    }

    public function canOrchestrate(): string
    {
        return 'photo';
    }

    public function execute(Multimedia $multimedia): array
    {
        /** @var MultimediaPhoto $multimedia */
        $photo = $this->queryMultimediaClient->findPhotoById($multimedia->resourceId()->id());

        return [
            $multimedia->id()->id() => [
                'opening' => $multimedia,
                'resource' => $photo,
            ],
        ];
    }
}
