<?php

/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body\DataProvider;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class BodyTagInsertedNewsDataProvider
{
    public function getData(): array
    {
        return [
            'no-signature' => [
                [],
            ],
            'one-signature' => [
                [
                    'journalistId' => 'journalistId',
                    'aliasId' => 'aliasId',
                    'name' => 'name',
                    'url' => 'url',
                    'departments' => [
                        [
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    'photo' => 'photo',
                ],
            ],
            'two-signatures' => [
                [
                    'journalistId' => 'journalistId_1',
                    'aliasId' => 'aliasId_1',
                    'name' => 'name_1',
                    'url' => 'url_1',
                    'departments' => [
                        [
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    'photo' => 'photo_1',
                ],
                [
                    'journalistId' => 'journalistId_2',
                    'aliasId' => 'aliasId_2',
                    'name' => 'name_2',
                    'url' => 'url_2',
                    'departments' => [
                        [
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    'photo' => 'photo_2',
                ],
            ],
        ];
    }
}
