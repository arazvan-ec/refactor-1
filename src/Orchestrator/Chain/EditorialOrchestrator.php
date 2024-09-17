<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Orchestrator\Chain\Orchestrator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements Orchestrator
{
    public function execute(Request $request): array
    {
        // TODO: Implement execute() method.
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }
}
