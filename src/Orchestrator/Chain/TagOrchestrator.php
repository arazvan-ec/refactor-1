<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Orchestrator\Chain\Orchestrator;
use Ec\Tag\Infrastructure\Client\Http\QueryTagClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class TagOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryTagClient $queryTagClient,
    ) {
    }

    public function execute(Request $request): array
    {
        $tagIds = $request->get('tags');
        $tags = [];

        foreach ($tagIds as $tagId) {
            $tags[] = $this->queryTagClient->findTagById($tagId);
        }

        return $tags;
    }

    public function canOrchestrate(): string
    {
        return 'tag';
    }
}
