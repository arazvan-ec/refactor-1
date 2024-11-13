<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer;

use App\Application\DataTransformer\BodyDataTransformer;
use App\Application\DataTransformer\BodyElementDataTransformerHandler;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Exceptions\BodyDataTransformerNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 * @covers \App\Application\DataTransformer\BodyDataTransformer
 */
class BodyDataTransformerTest extends TestCase
{
    /** @var BodyElementDataTransformerHandler|MockObject */
    private BodyElementDataTransformerHandler $bodyElementDataTransformerHandler;

    private BodyDataTransformer $bodyDataTransformer;

    protected function setUp(): void
    {
        $this->bodyElementDataTransformerHandler = $this->createMock(BodyElementDataTransformerHandler::class);
        $this->bodyDataTransformer = new BodyDataTransformer($this->bodyElementDataTransformerHandler);
    }

    protected function tearDown(): void
    {
        unset($this->bodyElementDataTransformerHandler, $this->bodyDataTransformer);
    }

    /**
     * @test
     */
    public function executeTransformsBodyElementsAndReturnArrayWithElements(): void
    {
        $bodyType = 'elementType';
        $resolveData = ['key' => 'value'];
        $transformedElement = ['transformed' => 'data'];

        $body = $this->createMock(Body::class);
        $body->method('type')->willReturn($bodyType);

        $bodyElement = $this->createMock(BodyElement::class);
        $body->method('getArrayCopy')->willReturn([$bodyElement]);

        $this->bodyElementDataTransformerHandler
            ->method('execute')
            ->with($bodyElement, $resolveData)
            ->willReturn($transformedElement);

        $result = $this->bodyDataTransformer->execute($body, $resolveData);

        static::assertSame(['type' => $bodyType, 'elements' => [$transformedElement]], $result);
    }

    /**
     * @test
     */
    public function executeSkipsElementsWithoutTransformer(): void
    {
        $bodyType = 'elementType';
        $resolveData = [];

        $bodyElement = $this->createMock(BodyElement::class);

        $body = $this->createMock(Body::class);
        $body->method('type')->willReturn($bodyType);
        $body->method('getArrayCopy')->willReturn([$bodyElement]);


        $this->bodyElementDataTransformerHandler
            ->method('execute')
            ->willThrowException(new BodyDataTransformerNotFoundException());

        $result = $this->bodyDataTransformer->execute($body, $resolveData);

        static::assertSame(['type' => $bodyType, 'elements' => []], $result);
    }
}
