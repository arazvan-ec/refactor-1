<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Orchestrator\Chain\Orchestrator;
use Ec\Section\Infrastructure\Client\Http\QuerySectionClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class SectionOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QuerySectionClient $querySectionClient,
    ) {
    }

    public function execute(Request $request): array
    {
        return $this->querySectionClient->findSectionById($request->get('sectionId'));
    }

    public function canOrchestrate(): string
    {
        return 'section';
    }
}
