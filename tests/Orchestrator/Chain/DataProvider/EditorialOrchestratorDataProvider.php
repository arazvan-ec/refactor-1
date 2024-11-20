<?php

namespace App\Tests\Orchestrator\Chain\DataProvider;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class EditorialOrchestratorDataProvider
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
