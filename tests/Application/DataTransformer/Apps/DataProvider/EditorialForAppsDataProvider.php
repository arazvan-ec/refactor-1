<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\DataProvider;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialForAppsDataProvider
{
    /**
     *
     * {
     * "journalistId": "5164",
     * "aliasId": "20116",
     * "name": "jmoreu",
     * "url": "https://www.elconfidencial.dev/autores/jose-guillermo-moreu-peso-5164/",
     * "departments": [
     * {
     * "id": "1",
     * "name": "Técnico"
     * }
     * ],
     * "photo": "https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png"
     * }
     */



    public function getJournalists(): array
    {
        return [
            'no-journalist'=> [
                [
                    'journalistId'=>'journalistId',
                    'aliasId'=>'aliasId',
                    'name'=>'name',
                    'url'=>'url',
                    'departments'=>[
                        [
                            'id'=>'id',
                            'name'=>'name'
                        ]
                    ],
                    'photo'=>'photo',

                ],
                [],
                []
            ],
            'one-journalist'=> [
                [],
                []

            ],
            'two-journalist'=> [
                [],
                []

            ],
        ];
    }
}
