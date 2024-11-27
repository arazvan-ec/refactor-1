<?php
/**
 * @copyright
 */

namespace App\Controller\V1\Schemas\BodyTags;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SubHead',
    title: 'SubHead',
    description: 'SubHead',
    properties: [
        new OA\Property(
            property: 'type',
            description: 'SubHead',
            type: 'string',
            enum: ['subhead']
        ),
        new OA\Property(
            property: 'content',
            description: 'SubHead content',
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
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class SubHead
{
}
