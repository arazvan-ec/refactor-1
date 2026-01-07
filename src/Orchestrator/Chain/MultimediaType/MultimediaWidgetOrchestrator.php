<?php

/**
 * @copyright
 */
namespace App\Orchestrator\Chain\MultimediaType;

use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class MultimediaWidgetOrchestrator implements MultimediaTypeOrchestratorInterface
{
    public function canOrchestrate(): string
    {
        return 'widget';
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
