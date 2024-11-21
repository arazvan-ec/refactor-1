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
            'inserted-news-with-one-signature' => [
                [
                    'signatures' => [
                        [
                            'journalistId' => '5164',
                            'aliasId' => '20116',
                            'name' => 'jmoreu',
                            'url' => 'https://www.elconfidencial.dev/autores/jose-guillermo-moreu-peso-5164/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                    ],
                    'title' => 'title body tag inserted news',
                    'editorial' => 'https://www.elconfidencial.dev/_editorial-456',
                    'photo' => '',
                    'signaturesIndexes' => [
                        '20116',
                    ],
                ],
                [
                    'signaturesWithIndexId' => [
                        '20116' => [
                            'journalistId' => '5164',
                            'aliasId' => '20116',
                            'name' => 'jmoreu',
                            'url' => 'https://www.elconfidencial.dev/autores/jose-guillermo-moreu-peso-5164/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                    ],
                ],
                // expected
                [
                    'type' => 'bodytaginsertednews',
                    'editorialId' => 'editorial_id',
                    'title' => 'title body tag inserted news',
                    'signatures' => [
                        [
                            'journalistId' => '5164',
                            'aliasId' => '20116',
                            'name' => 'jmoreu',
                            'url' => 'https://www.elconfidencial.dev/autores/jose-guillermo-moreu-peso-5164/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                    ],
                    'editorial' => 'https://www.elconfidencial.dev/_editorial_id',
                    'photo' => [

                    ],
                ],
            ],
            'inserted-news-with-two-signature' => [
                [
                    'signatures' => [
                        [
                            'journalistId' => '5164',
                            'aliasId' => '20116',
                            'name' => 'jmoreu',
                            'url' => 'https://www.elconfidencial.dev/autores/jose-guillermo-moreu-peso-5164/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                        [
                            'journalistId' => '5165',
                            'aliasId' => '20117',
                            'name' => 'another-author',
                            'url' => 'https://www.elconfidencial.dev/autores/another-author-5165/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                    ],
                    'title' => 'title body tag inserted news',
                    'editorial' => 'https://www.elconfidencial.dev/_editorial-456',
                    'photo' => '',
                    'signaturesIndexes' => [
                        '20116',
                        '20117',
                    ],
                ],
                [
                    'signaturesWithIndexId' => [
                        '20116' => [
                            'journalistId' => '5164',
                            'aliasId' => '20116',
                            'name' => 'jmoreu',
                            'url' => 'https://www.elconfidencial.dev/autores/jose-guillermo-moreu-peso-5164/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                        '20117' => [
                            'journalistId' => '5165',
                            'aliasId' => '20117',
                            'name' => 'another-author',
                            'url' => 'https://www.elconfidencial.dev/autores/another-author-5165/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                    ],
                ],
                // expected
                [
                    'type' => 'bodytaginsertednews',
                    'editorialId' => 'editorial_id',
                    'title' => 'title body tag inserted news',
                    'signatures' => [
                        [
                            'journalistId' => '5164',
                            'aliasId' => '20116',
                            'name' => 'jmoreu',
                            'url' => 'https://www.elconfidencial.dev/autores/jose-guillermo-moreu-peso-5164/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                        [
                            'journalistId' => '5165',
                            'aliasId' => '20117',
                            'name' => 'another-author',
                            'url' => 'https://www.elconfidencial.dev/autores/another-author-5165/',
                            'photo' => 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png',
                            'departments' => [
                                [
                                    'id' => '1',
                                    'name' => 'Técnico',
                                ],
                            ],
                        ],
                    ],
                    'editorial' => 'https://www.elconfidencial.dev/_editorial_id',
                    'photo' => [

                    ],
                ],
            ],
        ];
    }
}
