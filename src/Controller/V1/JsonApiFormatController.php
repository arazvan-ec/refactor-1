<?php
/**
 * @copyright
 */

namespace App\Controller\V1;

use App\Orchestrator\OrchestratorChain;
use Ec\MicroserviceBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
abstract class JsonApiFormatController extends AbstractController
{
    public function __construct(
        private readonly int $sMaxAge = 0,
        protected readonly OrchestratorChain $orchestratorChain
    ) {
        parent::__construct($this->sMaxAge, 'v1.0.0');
    }

    protected function buildResponse(string $type, array $data): Response
    {
        $body = [
            'data' => [
                'type' => $type,
                'attributes' => $data,
            ],
        ];

        return new JsonResponse($body);
    }

    protected function executeHandler(string $orchestratorType, string $responseType, Request $request): Response
    {
        $result = $this->orchestratorChain->handler($orchestratorType, $request);

        return $this->buildResponse($responseType, $result);
    }
}
