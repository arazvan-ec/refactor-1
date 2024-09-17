<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use Ec\Editorial\Domain\Model\Editorial;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class LegacyOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryLegacyClient $queryLegacyClient
    ) {
    }

    /**
     * @throws Throwable
     */
    public function execute(Request $request): array
    {
        $id = $request->get('id');

        /** @var Editorial|null $editorial */
        $editorial = $this->queryLegacyClient->findEditorialById($id);

        if (!is_null($editorial)) {
            return ['editorial' => $editorial];
        }

        throw new NotFoundHttpException('Editorial data not found for id: '.$id);
    }

    public function canOrchestrate(): string
    {
        return 'legacy';
    }
}
