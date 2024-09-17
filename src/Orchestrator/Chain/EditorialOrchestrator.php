<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements Orchestrator
{
    public function __construct(

    ) {
    }

    public function execute(Request $request): array
    {
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }
}
