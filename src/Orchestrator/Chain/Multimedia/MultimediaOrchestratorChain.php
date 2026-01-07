<?php

/**
 * @copyright
 */

namespace App\Orchestrator\Chain\Multimedia;

use App\Orchestrator\Chain\EditorialOrchestratorInterface;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
interface MultimediaOrchestratorChain
{
    /**
     * @return array<string, mixed>
     */
    public function handler(Multimedia $multimedia): array;

    public function addOrchestrator(MultimediaOrchestratorInterface $orchestrator): void;
}
