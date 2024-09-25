<?php

namespace App\Application\DataTransformer\Apps;


use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\Journalists;
use Ec\Section\Domain\Model\Section;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class DetailsAppsDataTransformer implements AppsDatatransformer
{
    private Editorial $editorial;

    private Journalists $journalists;

    private Section $section;

    public function __construct(string $extension)
    {
        $this->extension = $extension;
    }


    public function write(Editorial $editorial, Journalists $journalists,Section $section): DetailsAppsDataTransformer
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

      return $editorial;
    }

    private function transformerEditorial() : array
    {
        return ['id' => $this->editorial->id()->id()];
    }

    private function transformerJournalists(): array
    {
        $signatures = [];
        /** @var Signature $signature */
        foreach ($this->editorial->signatures() as $signature){

            /** @var Journalist $journalist */
            foreach ($this->journalists as $journalist){
                /** @var Alias $alias */
                foreach ($journalist->aliases() as $alias){
                    if($alias->id()->id() === $signature->id()->id()){

                        $departments = [];

                        foreach ($journalist->departments() as $department){
                            $departments[] = [
                                'id' => $department->id()->id(),
                                'name' => $department->name()
                            ];
                        }

                        $signatures[]= [
                            'journalistId' => $journalist->id()->id(),
                            'aliasId' => $alias->id()->id(),
                            'name' => $alias->name(),
                            'url' => sprintf('https://www.%s.%s/autores/%s-%s/',
                                SitesEnum::getHostnameById($this->section->siteId()),
                                $this->extension,
                                urlencode($journalist->name()),
                                $journalist->id()->id()
                            ),
                            'photo' => $journalist->photo(),
                            'departments' => $departments


                        ];
                    }

                }
            }
        }
        return $signatures;
    }


}
