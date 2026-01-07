<?php

/**
 * @copyright
 */
namespace App\Orchestrator\Chain\MultimediaType;

use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Domain\Model\Multimedia\QueryMultimediaClient;
use Ec\Multimedia\Domain\Model\Multimedia\ResourceId;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class MultimediaPhotoOrchestrator implements MultimediaTypeOrchestratorInterface
{
    public function __construct(
        private readonly \Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient $queryMultimediaClient,
    ) {
    }
    public function canOrchestrate(): string
    {
        return 'photo';
    }

    public function execute(Multimedia $multimedia): array
    {
        /** @var ResourceId $resource */
        $resource = $this->queryMultimediaClient->findPhotoById($multimedia->resourceId());

        return [
            $multimedia->id()->id() => [
                'opening' => $multimedia,
                'resource' => $resource,
            ],
        ];
    }
}
