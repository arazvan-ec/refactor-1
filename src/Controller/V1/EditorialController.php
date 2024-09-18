<?php
/**
 * @copyright
 */

namespace App\Controller\V1;

use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Orchestrator\OrchestratorChain;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\MicroserviceBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialController extends AbstractController
{
    public function __construct(
        private readonly OrchestratorChain $orchestratorChain,
        private readonly int $sMaxAge = 0,
    ) {
        parent::__construct($this->sMaxAge, 'v1.0.0');
    }

    public function getEditorialById(Request $request, string $id): JsonResponse
    {
        $request->attributes->set('id', $id);

        return new JsonResponse($this->orchestratorChain->handler('editorial', $request));
    }
}
