<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain\Multimedia;

use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
interface MultimediaOrchestratorInterface
{
    public function execute(Multimedia $multimedia): array;
    public function canOrchestrate(): string;
}
