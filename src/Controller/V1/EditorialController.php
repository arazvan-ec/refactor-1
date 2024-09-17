<?php
/**
 * @copyright
 */

namespace App\Controller\V1;

use App\Orchestrator\OrchestratorChain;
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

        $editorialData = $this->orchestratorChain->handler('editorial', $request);
    }
}
