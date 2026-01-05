<?php

/**
 * @copyright
 */

namespace App\Orchestrator\Chain;


use Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient as QueryMultimediaOpeningClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class MediaOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryMultimediaOpeningClient $queryMultimediaOpeningClient,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(Request $request): array
    {

        return [];
    }

    public function canOrchestrate(): string
    {
        return 'media';
    }
}
