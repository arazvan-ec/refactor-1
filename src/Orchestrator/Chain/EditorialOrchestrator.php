<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDatatransformer;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Infrastructure\Enum\SitesEnum;
use App\Orchestrator\Trait\SectionTrait;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Journalist\Application\Service\JournalistFactory;
use Ec\Journalist\Infrastructure\Client\Http\QueryJournalistClient;
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
        private readonly QueryJournalistClient $queryJournalistClient,
        private readonly QuerySectionClient $querySectionClient,
        private readonly JournalistFactory $journalistFactory,
        private readonly AppsDatatransformer $detailsAppsDataTransformer,
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
        $journalists = $this->journalistFactory->buildJournalists();

        foreach ($editorial->signatures() as $signature) {
            $aliasId= $this->journalistFactory->buildAliasId($signature->id()->id());
            $journalist= $this->queryJournalistClient->findJournalistByAliasId($aliasId);

            if ($journalist->isActive() && $journalist->isVisible())
            $journalists->addItem($journalist);
        }

        $section = $this->getSectionById($editorial->sectionId());

        return $this->detailsAppsDataTransformer->write($editorial,$journalists,$section)->read();

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
