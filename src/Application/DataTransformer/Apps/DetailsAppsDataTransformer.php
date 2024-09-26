<?php

namespace App\Application\DataTransformer\Apps;


use App\Infrastructure\Service\Thumbor;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Section\Domain\Model\Section;


/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class DetailsAppsDataTransformer implements AppsDataTransformer
{

    use UrlGeneratorTrait;

    private Editorial $editorial;
    private array $journalists;

    private Section $section;
    private Thumbor $thumbor;


    public function __construct(string $extension,Thumbor $thumbor)
    {
        $this->thumbor = $thumbor;
        $this->setExtension($extension);
    }

    public function write(Editorial $editorial, array $journalists, Section $section): DetailsAppsDataTransformer
    {
        $this->editorial = $editorial;
        $this->journalists = $journalists;
        $this->section = $section;

        return $this;
    }

    public function read(): array
    {
        $editorial = $this->transformerEditorial();
        $editorial['signatures'] = $this->transformerJournalists();
        $editorial['section'] = $this->transformerSection();

        return $editorial;
    }

    private function transformerEditorial(): array
    {
        return ['id' => $this->editorial->id()->id()];
    }

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
                        'url' => $this->journalistUrl($alias, $this->section, $journalist),
                        'photo' => $this->photoUrl($journalist),
                        'departments' => $departments,


                    ];
                }
            }

        }

        return $signatures;
    }

    private function journalistUrl(Alias $alias, Section $section, Journalist $journalist): string
    {
        if ($alias->private()){
            return $this->generateUrl(
                'https://%s.%s.%s/%s',
                $this->section->isBlog() ? 'blog' : 'www',
                $this->section->siteId(),
                $this->section->getPath());
        }

            return  $this->generateUrl(
            'https://%s.%s.%s/autores/%s/',
            'www',
            $this->section->siteId(),
            sprintf('%s-%s', urlencode($journalist->name()), $journalist->id()->id())
        );
    }

    private function photoUrl(Journalist $journalist) : string
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


}
