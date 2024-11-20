<?php

namespace App\Tests\Orchestrator\Chain\DataProvider;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class EditorialOrchestratorDataProvider
{
    public function getBodyExpected(): array
    {
        $allJournalist =  [
            '1' => [
                'journalistId' => '1',
                'aliasId' => '1',
                'name' => 'Javier Bocanegra 1',
                'url' => 'https://www.elconfidencial.dev/autores/Javier+Bocanegra-2338/',
                'photo' => 'https://images.ecestaticos.dev/K0FFtVTsHaYc4Yd0feIi_Oiu6O4=/dev.f.elconfidencial.com/journalist/1b2/c5e/4ff/1b2c5e4fff467ca4e86b6aa3d3ded248.jpg',
                'departments' => [
                    [
                        'id' => '11',
                        'name' => 'Fin de semana',
                    ],
                ],
            ],
            '2' => [
                'journalistId' => '2',
                'aliasId' => '2',
                'name' => 'Javier Bocanegra 1',
                'url' => 'https://www.elconfidencial.dev/autores/Javier+Bocanegra-2338/',
                'photo' => 'https://images.ecestaticos.dev/K0FFtVTsHaYc4Yd0feIi_Oiu6O4=/dev.f.elconfidencial.com/journalist/1b2/c5e/4ff/1b2c5e4fff467ca4e86b6aa3d3ded248.jpg',
                'departments' => [
                    [
                        'id' => '11',
                        'name' => 'Fin de semana',
                    ],
                ],
            ],
            '5' => [
                'journalistId' => '5',
                'aliasId' => '5',
                'name' => 'Javier Bocanegra 1',
                'url' => 'https://www.elconfidencial.dev/autores/Javier+Bocanegra-2338/',
                'photo' => 'https://images.ecestaticos.dev/K0FFtVTsHaYc4Yd0feIi_Oiu6O4=/dev.f.elconfidencial.com/journalist/1b2/c5e/4ff/1b2c5e4fff467ca4e86b6aa3d3ded248.jpg',
                'departments' => [
                    [
                        'id' => '11',
                        'name' => 'Fin de semana',
                    ],
                ],
            ],
            '6' => [
                'journalistId' => '6',
                'aliasId' => '6',
                'name' => 'Javier Bocanegra 1',
                'url' => 'https://www.elconfidencial.dev/autores/Javier+Bocanegra-2338/',
                'photo' => 'https://images.ecestaticos.dev/K0FFtVTsHaYc4Yd0feIi_Oiu6O4=/dev.f.elconfidencial.com/journalist/1b2/c5e/4ff/1b2c5e4fff467ca4e86b6aa3d3ded248.jpg',
                'departments' => [
                    [
                        'id' => '11',
                        'name' => 'Fin de semana',
                    ],
                ],
            ],
            '7' => [
                'journalistId' => '7',
                'aliasId' => '7',
                'name' => 'Javier Bocanegra 1',
                'url' => 'https://www.elconfidencial.dev/autores/Javier+Bocanegra-2338/',
                'photo' => 'https://images.ecestaticos.dev/K0FFtVTsHaYc4Yd0feIi_Oiu6O4=/dev.f.elconfidencial.com/journalist/1b2/c5e/4ff/1b2c5e4fff467ca4e86b6aa3d3ded248.jpg',
                'departments' => [
                    [
                        'id' => '11',
                        'name' => 'Fin de semana',
                    ],
                ],
            ],
        ];

        return [
            'case1' => [
                [
                    'id' => 'editorialId',
                    'sectionId' => 'editorialSectionId',
                    'signatures' => ['1', '2'],
                    'insertedNews' => [
                        [
                            'id' => '3',
                            'sectionId' => 'sectionId3',
                            'signatures' => ['5', '6'],
                            'multimediaId' => '56',
                        ],
                        [
                            'id' => '4',
                            'sectionId' => 'sectionId4',
                            'signatures' => ['7'],
                            'multimediaId' => '69',
                        ],
                    ],
                    'membershipCards' => [],
                ],
                $allJournalist,
            ],
        ];
    }
}
