<?php

namespace App\Orchestrator\Chain\Multimedia;

use App\Orchestrator\Exceptions\OrchestratorTypeNotExistException;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;

class MultimediaOrchestratorHandler implements MultimediaOrchestratorChain
{
    /**
     * @param MultimediaOrchestratorInterface[] $orchestrators
     */
    private array $orchestrators = [];

    /**
     * @throws OrchestratorTypeNotExistException
     * @return array<string, mixed>
     */
    public function handler(Multimedia $multimedia): array
    {
        if (!\array_key_exists($multimedia->type(), $this->orchestrators)) {
            throw new OrchestratorTypeNotExistException('Orchestrator '.$multimedia->type().' not exist');
        }

        return $this->orchestrators[$multimedia->type()]->execute($multimedia);
    }

    public function addOrchestrator(MultimediaOrchestratorInterface $orchestrator): void
    {
        $key = $orchestrator->canOrchestrate();
        if (isset($this->orchestrators[$key])) {
            throw new \Exception("$key orchestrator duplicate.");
        }
        $this->orchestrators[$key] = $orchestrator;
    }
}
