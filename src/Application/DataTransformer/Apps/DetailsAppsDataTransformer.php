<?php

namespace App\Application\DataTransformer\Apps;

use App\Infrastructure\Enum\SitesEnum;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\Journalists;
use Ec\Section\Domain\Model\Section;
use Thumbor\Url\BuilderFactory;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class DetailsAppsDataTransformer implements AppsDataTransformer
{

    use UrlGeneratorTrait;

    private Editorial $editorial;
    private Journalists $journalists;

    private Section $section;



    public function __construct(string $extension, string $thumborServerUrl, string $thumborSecret, string $awsBucket)
    {
        $this->thumborServerUrl = $thumborServerUrl;
        $this->thumborSecret = $thumborSecret;
        $this->awsBucket = $awsBucket;
        $this->thumborFactory = BuilderFactory::construct($thumborServerUrl, $thumborSecret);

        $this->setExtension($extension);
    }

    public function write(Editorial $editorial, Journalists $journalists, Section $section): DetailsAppsDataTransformer
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
        /** @var Signature $signature */
        foreach ($this->editorial->signatures() as $signature) {

            /** @var Journalist $journalist */
            foreach ($this->journalists as $journalist) {

                /** @var Alias $alias */
                foreach ($journalist->aliases() as $alias) {
                    if ($alias->id()->id() === $signature->id()->id()) {

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
                            'url' => ($alias->private())
                                ?  $this->generateUrl(
                                    'https://%s.%s.%s/%s',
                                    $this->section->isBlog() ? 'blog' : 'www',
                                    $this->section->siteId(),
                                    $this->section->getPath()
                                )
                                : $this->generateUrl(
                                    'https://%s.%s.%s/autores/%s/',
                                    'www',
                                    $this->section->siteId(),
                                    sprintf('%s-%s', urlencode($journalist->name()), $journalist->id()->id())
                                ),
                            'photo' => $this->photoUrl($journalist),
                            'departments' => $departments,


                        ];
                    }

                }
            }
        }

        return $signatures;
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
       return $this->thumborFactory->url($this->createOriginalAWSImage($photo));


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

    private function createOriginalAWSImage(string $fileImage): string
    {
        $path1 = \substr($fileImage, 0, 3);
        $path2 = \substr($fileImage, 3, 3);
        $path3 = \substr($fileImage, 6, 3);

        return $this->awsBucket."/journalist/{$path1}/{$path2}/{$path3}/{$fileImage}";
    }
}
