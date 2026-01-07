<?php

/**
 * @copyright
 */

namespace App\Orchestrator\Chain\Multimedia;

use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaWidget;
use Ec\Widget\Domain\Model\EveryWidget;
use Ec\Widget\Domain\Model\QueryWidgetClient;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class MultimediaWidgetOrchestrator implements MultimediaOrchestratorInterface
{
    public function __construct(
        private readonly QueryWidgetClient $queryWidgetClient,
    ) {
    }

    public function canOrchestrate(): string
    {
        return 'widget';
    }

    public function execute(Multimedia $multimedia): array
    {
        /**
         * @var MultimediaWidget $multimedia
         * @var EveryWidget      $widget
         */
        $widget = $this->queryWidgetClient->findWidgetById($multimedia->resourceId()->id());

        return [
            $multimedia->id()->id() => [
                'opening' => $multimedia,
                'resource' => $widget,
            ],
        ];
    }
}
