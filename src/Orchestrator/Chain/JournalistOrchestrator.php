<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Orchestrator\Chain\Orchestrator;
use Ec\Journalist\Infrastructure\Client\Http\QueryJournalistClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class JournalistOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryJournalistClient $queryJournalistClient,
    ) {
    }

    public function execute(Request $request): array
    {
        $journalistIds = $request->get('journalists');
        $journalists = [];

        foreach ($journalistIds as $journalistId) {
            $this->queryJournalistClient->findJournalistById($journalistId);
        }

        return $journalists;
    }

    public function canOrchestrate(): string
    {
        return 'journalist';
    }
}
