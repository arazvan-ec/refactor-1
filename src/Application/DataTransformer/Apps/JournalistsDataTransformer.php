<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use App\Infrastructure\Service\Thumbor;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Editorial\Domain\Model\Signatures;
use Ec\Encode\Encode;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\Journalists;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Section\Domain\Model\Section;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
class JournalistsDataTransformer implements JournalistDataTransformer
{
    use UrlGeneratorTrait;
    public function __construct(
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QueryJournalistClient $queryJournalistClient,
        private readonly JournalistFactory $journalistFactory,
        string $extension,
        private readonly Thumbor $thumbor,
    )
    {
        $this->setExtension($extension);
    }

    public function write(Editorial $editorial, Section $section): JournalistsDataTransformer
    {
        $this->editorial = $editorial;
        $this->section = $section;

        return $this;
    }

    public function read(): array
    {
        /** @var Journalists $journalists */
        $journalists = [];

        $editorialSignatures = $this->editorial->signatures();

        /** @var BodyTagInsertedNews[] $insertedNews */
        $insertedNews = $this->editorial->body()->bodyElementsOf(BodyTagInsertedNews::class);

        for ($i = 0; $i < count($insertedNews); ++$i) {
            $id = $insertedNews[$i]->editorialId()->id();

            /** @var Editorial $editorial */
            $editorial = $this->queryEditorialClient->findEditorialById($id);

            foreach ($editorial->signatures() as $signature) {
                if (!$this->hasSignature($editorialSignatures, $signature->id()->id())) {
                    $editorialSignatures->addItem($signature);
                }
            }
        }

        foreach ($editorialSignatures as $signature) {
            $aliasId = $this->journalistFactory->buildAliasId($signature->id()->id());

            /** @var Journalist $journalist */
            $journalist = $this->queryJournalistClient->findJournalistByAliasId($aliasId);

            if ($journalist->isActive() && $journalist->isVisible()) {
                // Todo: fix index object
                $journalists[$aliasId->id()] = $journalist;
            }
        }

        return $this->transformerJournalists($journalists);
    }

    /**
     * @return Journalist[] $journalists
     */
    private function transformerJournalists(array $journalists): array
    {
        $signatures = [];

        foreach ($journalists as $aliasId => $journalist) {
            foreach ($journalist->aliases() as $alias) {

                if ($alias->id()->id() == $aliasId) {
                    $signature = [
                        'journalistId' => $journalist->id()->id(),
                        'aliasId' => $alias->id()->id(),
                        'name' => $alias->name(),
                        'url' => $this->journalistUrl($alias, $journalist),
                    ];

                    $photo = $this->photoUrl($journalist);
                    if ('' !== $photo) {
                        $signature['photo'] = $photo;
                    }

                    $departments = [];
                    foreach ($journalist->departments() as $department) {
                        $departments[] = [
                            'id' => $department->id()->id(),
                            'name' => $department->name(),
                        ];
                    }

                    $signature['departments'] = $departments;

                    $signatures[$alias->id()->id()] = $signature;
                }
            }
        }

        return $signatures;
    }


    private function journalistUrl(Alias $alias, Journalist $journalist): string
    {
        if ($alias->private()) {
            return $this->generateUrl(
                'https://%s.%s.%s/%s',
                $this->section->isBlog() ? 'blog' : 'www',
                $this->section->siteId(),
                $this->section->getPath()
            );
        }

        return  $this->generateUrl(
            'https://%s.%s.%s/autores/%s/',
            'www',
            $this->section->siteId(),
            sprintf('%s-%s', Encode::encodeUrl($journalist->name()), $journalist->id()->id())
        );
    }


    private function photoUrl(Journalist $journalist): string
    {
        if (!empty($journalist->blogPhoto())) {
            return $this->thumbor->createJournalistImage($journalist->blogPhoto());
        }

        if (!empty($journalist->photo())) {
            return $this->thumbor->createJournalistImage($journalist->photo());
        }

        return '';
    }

    private function hasSignature(Signatures $signatures, string $aliasId): bool
    {
        foreach ($signatures->getArrayCopy() as $signature) {
            if ($signature->id()->id() == $aliasId) {
                return true;
            }
        }

        return false;
    }
}
