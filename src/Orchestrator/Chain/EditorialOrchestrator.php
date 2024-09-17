<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryEditorialClient $queryEditorialClient
    ) {
    }

    public function execute(Request $request): array
    {
        $id = $request->get('id');

        /** @var Editorial|null $editorial */
        $editorial = $this->queryEditorialClient->findEditorialById($id);

        if (!is_null($editorial)) {
            return ['editorial' => $editorial];
        }

        throw new NotFoundHttpException('Editorial data not found for id: '.$id);
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }
}
