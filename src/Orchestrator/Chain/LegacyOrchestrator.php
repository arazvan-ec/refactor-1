<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Orchestrator\Chain\Orchestrator;
use Ec\Editorial\Domain\Model\Editorial;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class LegacyOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryEditorial $queryEditorialClient
    ) {
    }
    public function execute(Request $request): array
    {
        $id = $request->get('id');

        /** @var Editorial|null $editorial */
        $editorial = $this->queryEditorialClient->get($id);

        if (null === $editorial) {
            throw new NotFoundHttpException('Editorial data not found for id: '.$id);
        }
    }

    public function canOrchestrate(): string
    {
        return 'legacy';
    }
}
