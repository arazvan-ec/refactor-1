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
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QueryLegacyClient $queryLegacyClient,
        private readonly int $sMaxAge = 0,
    ) {
        parent::__construct($this->sMaxAge, 'v1.0.0');
    }

    public function getEditorialById(Request $request, string $id): JsonResponse
    {
        $request->attributes->set('id', $id);
            // implementar subscriber de excepciones en vez de try/catch
        try {
            /** @var Editorial|null $editorial */
            $editorial = $this->queryEditorialClient->findEditorialById($id);
            $data = $this->orchestratorChain->handler('editorial', $request);
        } catch (\Throwable $exception) {
            // lo que sea
        }

        if (is_null($editorial->sourceEditorial()) {
            $data = $this->queryLegacyClient->findEditorialById($id);
        }

        return new JsonResponse($data);
    }
}
