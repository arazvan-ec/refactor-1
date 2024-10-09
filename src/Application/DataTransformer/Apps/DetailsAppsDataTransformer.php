<?php

namespace App\Application\DataTransformer\Apps;

use App\Application\DataTransformer\BodyElementDataTransformerHandler;
use App\Infrastructure\Enum\ClossingModeEnum;
use App\Infrastructure\Enum\EditorialTypesEnum;
use App\Infrastructure\Service\Thumbor;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Exceptions\BodyDataTransformerNotFoundException;
use Ec\Encode\Encode;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\Tag;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class DetailsAppsDataTransformer implements AppsDataTransformer
{
    use UrlGeneratorTrait;

    private Editorial $editorial;

    /** @var Journalist[] */
    private array $journalists;

    private Section $section;

    /** @var Tag[] */
    private array $tags;

    public function __construct(
        string $extension,
        private readonly Thumbor $thumbor,
        private readonly BodyElementDataTransformerHandler $bodyElementDataTransformerHandler,
    ) {
        $this->setExtension($extension);
    }

    /**
     * @param Journalist[] $journalists
     * @param Tag[]        $tags
     */
    public function write(
        Editorial $editorial,
        array $journalists,
        Section $section,
        array $tags,
    ): DetailsAppsDataTransformer {
        $this->editorial = $editorial;
        $this->journalists = $journalists;
        $this->section = $section;
        $this->tags = $tags;

        return $this;
    }

    public function read(): array
    {
        $editorial = $this->transformerEditorial();
        $editorial['signatures'] = $this->transformerJournalists();
        $editorial['section'] = $this->transformerSection();
        $editorial['tags'] = $this->transformerTags();

        return $editorial;
    }

    /**
     * @return array<string, array<string, mixed>|bool|int|string>
     */
    private function transformerEditorial(): array
    {
        $editorialType = EditorialTypesEnum::getNameById($this->editorial->editorialType());

        return
            [
                'id' => $this->editorial->id()->id(),
                'url' => $this->editorialUrl(),
                'titles' => [
                    'title' => $this->editorial->editorialTitles()->title(),
                    'preTitle' => $this->editorial->editorialTitles()->preTitle(),
                    'urlTitle' => $this->editorial->editorialTitles()->urlTitle(),
                    'mobileTitle' => $this->editorial->editorialTitles()->mobileTitle(),
                ],
                'lead' => $this->editorial->lead(),
                'publicationDate' => $this->editorial->publicationDate()->format('Y-m-d H:i:s'),
                'updatedOn' => $this->editorial->publicationDate()->format('Y-m-d H:i:s'),
                'endOn' => $this->editorial->endOn()->format('Y-m-d H:i:s'),
                'type' => [
                    'id' => $editorialType['id'],
                    'name' => $editorialType['name'],
                ],
                'indexable' => $this->editorial->indexed(),
                'deleted' => $this->editorial->isDeleted(),
                'published' => $this->editorial->isPublished(),
                'closingModeId' => ClossingModeEnum::getClosingModeById($this->editorial->closingModeId()),
                'commentable' => $this->editorial->canComment(),
                'isBrand' => $this->editorial->isBrand(),
                'isAmazonOnsite' => $this->editorial->isAmazonOnsite(),
                'contentType' => $this->editorial->contentType(),
                'canonicalEditorialId' => $this->editorial->canonicalEditorialId(),
                'urlDate' => $this->editorial->urlDate()->format('Y-m-d H:i:s'),
                'countWords' => $this->editorial->body()->countWords(),
                'caption' => $this->editorial->caption(),
                'body' => $this->transformerBody($this->editorial->body()),
            ];
    }

    /**
     * @return array<int<0, max>, array<string, mixed>>
     */
    private function transformerJournalists(): array
    {
        $signatures = [];

        foreach ($this->journalists as $aliasId => $journalist) {
            foreach ($journalist->aliases() as $alias) {

                if ($alias->id()->id() == $aliasId) {

                    $departments = [];

                    foreach ($journalist->departments() as $department) {
                        $departments[] = [
                            'id' => $department->id()->id(),
                            'name' => $department->name(),
                        ];
                    }

                    $signatures[] = [
                        'journalistId' => $journalist->id()->id(),
                        'aliasId' => $alias->id()->id(),
                        'name' => $alias->name(),
                        'url' => $this->journalistUrl($alias, $journalist),
                        'photo' => $this->photoUrl($journalist),
                        'departments' => $departments,
                    ];
                }
            }
        }

        return $signatures;
    }

    private function editorialUrl(): string
    {
        $editorialPath = $this->section->getPath().'/'.
            $this->editorial->publicationDate()->format('Y-m-d').'/'.
            Encode::encodeUrl($this->editorial->editorialTitles()->urlTitle()).'_'.
            $this->editorial->id()->id();

        return $this->generateUrl(
            'https://%s.%s.%s/%s',
            $this->section->isBlog() ? 'blog' : 'www',
            $this->section->siteId(),
            $editorialPath
        );
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
        $photo = '';
        if (!empty($journalist->blogPhoto())) {
            $photo = $journalist->blogPhoto();
        }
        if (!empty($journalist->photo())) {
            $photo = $journalist->photo();
        }

        return $this->thumbor->createJournalistImage($photo);
    }

    /**
     * @return array<string, string>
     */
    private function transformerSection(): array
    {
        $url = $this->generateUrl(
            'https://%s.%s.%s/%s',
            $this->section->isBlog() ? 'blog' : 'www',
            $this->section->siteId(),
            $this->section->getPath()
        );

        return [
            'id' => $this->section->id()->id(),
            'name' => $this->section->name(),
            'url' => $url,
        ];
    }

    /**
     * @return array<int<0, max>, array<string, mixed>>
     */
    private function transformerTags(): array
    {
        $result = [];
        foreach ($this->tags as $tag) {

            $urlPath = sprintf(
                '/tags/%s/%s-%s',
                Encode::encodeUrl($tag->type()->name()),
                Encode::encodeUrl($tag->name()),
                $tag->id()->id(),
            );

            $result[] = [
                'id' => $tag->id()->id(),
                'name' => $tag->name(),
                'url' => $this->generateUrl(
                    'https://%s.%s.%s/%s',
                    'www',
                    $this->section->siteId(),
                    $urlPath,
                ),
            ];
        }

        return $result;
    }

    /**
     * @return array<int<0, max>, array<string, mixed>>
     * @throws BodyDataTransformerNotFoundException
     */
    private function transformerBody(Body $body): array
    {
        $parsedBody = [
            'type' => $body->type(),
            'elements' => [],
        ];

        /** @var BodyElement $bodyElement */
        foreach ($body as $bodyElement) {
            $parsedBody['elements'][] = $this->bodyElementDataTransformerHandler->execute($bodyElement);
        }

        return $parsedBody;
    }
}
