<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use Ec\Editorial\Domain\Model\Editorial;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class LegacyOrchestrator implements Orchestrator
{
    public function execute(Request $request): array
    {
        // TODO: Implement execute() method.
    }

    public function canOrchestrate(): string
    {
        return 'legacy';
    }
}
