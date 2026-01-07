<?php

/**
 * @copyright
 */
namespace App\Orchestrator\Chain\Multimedia;

use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class MultimediaEmbedVideoOrchestrator implements MultimediaOrchestratorInterface
{
    public function canOrchestrate(): string
    {
        return 'embed_video';
    }

    public function execute(Multimedia $multimedia): array
    {
        return [
            $multimedia->id()->id() => [
                'opening' => $multimedia,
            ],
        ];
    }
}
