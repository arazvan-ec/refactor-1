<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Infrastructure\Enum\SitesEnum;
use App\Orchestrator\Trait\SectionTrait;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements Orchestrator
{
    use SectionTrait;

    public function __construct(
        private readonly QueryLegacyClient $queryLegacyClient,
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QuerySectionClient $querySectionClient,
        private readonly string $extension
    ) {
        $this->setSectionClient($querySectionClient);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function execute(Request $request): array
    {
        $id = $request->get('id');

        /** @var Editorial $editorial */
        $editorial = $this->queryEditorialClient->findEditorialById($id);

        if (null === $editorial->sourceEditorial()) {
            return $this->queryLegacyClient->findEditorialById($id);
        }

        $section = $this->getSectionById($editorial->sectionId());

        return [
            'editorial' => $editorial,
            'section' => $this->sectionDataTransformer($section),
        ];
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }

    private function sectionDataTransformer(Section $section): array
    {
        return [
            'section' => [
                'id' => $section->id()->id(),
                'name' => $section->name(),
                'url' => sprintf('https://%s.%s.%s/%s',
                    $section->isBlog() ? 'blog' : 'www',
                    SitesEnum::getHostnameById($section->siteId()),
                    $this->extension,
                    trim($section->getPath(), '/')),
            ]
        ];
    }
}
