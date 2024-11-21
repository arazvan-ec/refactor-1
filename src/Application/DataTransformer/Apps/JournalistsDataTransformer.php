<?php
/**
 * @copyright
 */

namespace App\Application\DataTransformer\Apps;

use App\Infrastructure\Service\Thumbor;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Encode\Encode;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Section\Domain\Model\Section;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
class JournalistsDataTransformer implements JournalistDataTransformer
{
    use UrlGeneratorTrait;

    public function __construct(
        string $extension,
        private readonly Thumbor $thumbor,
    ) {
        $this->setExtension($extension);
    }

    /**
     * @param Journalist[] $journalists
     *
     * @return $this
     */
    public function write(array $journalists, Section $section): JournalistsDataTransformer
    {
        $this->journalists = $journalists;
        $this->section = $section;

        return $this;
    }

    public function read(): array
    {
        return $this->transformerJournalists();
    }

    /**
     * @return Journalist[] $journalists
     */
    private function transformerJournalists(): array
    {
        $signatures = [];

        foreach ($this->journalists as $aliasId => $journalist) {
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
}
