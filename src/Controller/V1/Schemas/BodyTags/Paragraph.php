<?php
/**
 * @copyright
 */

namespace App\Controller\V1\Schemas\BodyTags;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Paragraph',
    title: 'Paragraph',
    description: 'Paragraph',
    properties: [
        new OA\Property(
            property: 'type',
            description: 'Paragraph',
            type: 'string',
            enum: ['paragraph']
        ),
        new OA\Property(
            property: 'content',
            description: 'Paragraph content',
            type: 'string',
        ),
        new OA\Property(
            property: 'links',
            description: 'Links',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(
                        property: 'replace{num#}',
                        properties: [
                            new OA\Property(
                                property: 'type',
                                type: 'string',
                                enum: ['link']
                            ),
                            new OA\Property(
                                property: 'content',
                                type: 'string'
                            ),
                            new OA\Property(
                                property: 'url',
                                type: 'string'
                            ),
                            new OA\Property(
                                property: 'target',
                                type: 'string'
                            ),
                        ],
                        type: 'object',
                    ),
                ]
            )
        ),
    ],
    type: 'object',
)]
/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class Paragraph
{
}
