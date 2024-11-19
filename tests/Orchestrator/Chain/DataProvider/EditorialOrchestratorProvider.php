<?php

namespace App\Tests\Orchestrator\Chain\DataProvider;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class EditorialOrchestratorProvider
{
    public function EditorialIsValid(): array
    {
        return [
            [false, new \DateTime('3000-01-01 00:00:00')],
            [true, new \DateTime('1900-01-01 00:00:00')],
        ];
    }

    public function getBodyExpected(): array
    {
        return [
            [
                [
                    'type' => 'normal',
                    'elements' => [
                        [
                            'buttons' => [
                                [
                                    'url' => 'https://www.amazon.es/Cecotec-Multifunci%C3%B3n-Funciones-Antiadherente-Accesorios1/dp/B0BJQPQVHP?tag=cacatuaMan',
                                    'urlMembership' =>  'https://www.amazon.es/Cecotec-Multifunci%C3%B3n-Funciones-Antiadherente-Accesorios2/dp/B0BJQPQVHP?tag=cacatuaMan',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getOrchestratorExpected(): array
    {
        return [
            'one-case' => [
                [],
                [],
                [],
                [],
                [],
            ],
        ];
    }
}
