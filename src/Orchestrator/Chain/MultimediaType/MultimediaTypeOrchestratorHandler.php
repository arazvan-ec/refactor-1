<?php

namespace App\Orchestrator\Chain\MultimediaType;

use App\Orchestrator\Exceptions\OrchestratorTypeNotExistException;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;

class MultimediaTypeOrchestratorHandler implements MultimediaOrchestratorChain
{
    /**
     * @param MultimediaTypeOrchestratorInterface[] $orchestrators
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

    public function addOrchestrator(MultimediaTypeOrchestratorInterface $orchestrator): void
    {
        $key = $orchestrator->canOrchestrate();
        if (isset($this->orchestrators[$key])) {
            throw new \Exception("$key orchestrator duplicate.");
        }
        $this->orchestrators[$key] = $orchestrator;
    }
}
