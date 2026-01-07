<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain\MultimediaType;

use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
interface MultimediaTypeOrchestratorInterface
{
    public function execute(Multimedia $multimediaData): array;
    public function canOrchestrate(): string;
}
