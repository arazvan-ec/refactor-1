<?php

/**
 * @copyright
 */

namespace App\Application\DataTransformer;

use Ec\Editorial\Domain\Model\Body\Body;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
interface BodyDataTransformerInterface
{
    public function execute(Body $body, array $resolveData): array;
}
