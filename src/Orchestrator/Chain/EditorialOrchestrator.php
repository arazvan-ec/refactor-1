<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryLegacyClient $queryLegacyClient,
        private readonly QueryEditorialClient $queryEditorialClient
    ) {
    }

    public function execute(Request $request): array
    {
        $id = $request->get('id');

        /** @var Editorial $editorial */
        $editorial = $this->queryEditorialClient->findEditorialById($id);

        if ($editorial->sourceEditorial() === null) {
            return $this->queryLegacyClient->findEditorialById($id);
        }

        return ['editorial' => ['id' => $editorial->id()->id()]];
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }
}
