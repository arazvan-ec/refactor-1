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
    public function execute(Multimedia $multimediaData): array;
    public function canOrchestrate(): string;
}
