<?php

namespace App\Orchestrator\Trait;

use Ec\Section\Domain\Model\Section;
use Ec\Section\Domain\Model\QuerySectionClient;

trait SectionTrait
{
    private QuerySectionClient $sectionClient;

    public function sectionClient(): QuerySectionClient
    {
        return $this->sectionClient;
    }

    private function setSectionClient(QuerySectionClient $sectionClient): void
    {
        $this->sectionClient = $sectionClient;
    }

    protected function getSectionById(string $id): ?Section
    {
        try {
            return $this->sectionClient->findSectionById($id);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
