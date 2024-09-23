<?php
/**
 * @copyright
 */

namespace App\Tests\EventListener;

use App\EventListener\ResponseListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\EventListener\ResponseListener
 */
class ResponseListenerTest extends TestCase
{
    /**
     * @test
     */
    public function onKernelResponseShouldIncludeXSystemHeader(): void
    {
        $kernelMock = $this->createMock(HttpKernelInterface::class);
        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createMock(Response::class);
        $exceptionSubscribed = new ResponseListener();

        $headerBagMock = $this->createMock(HeaderBag::class);
        $headerBagMock->expects(static::once())
            ->method('set')
            ->willReturnSelf();

        $reflection = new \ReflectionObject($responseMock);
        $property = $reflection->getProperty('headers');
        $property->setValue($responseMock, $headerBagMock);

        $eventResponse = new ResponseEvent($kernelMock, $requestMock, HttpKernelInterface::MAIN_REQUEST, $responseMock);
        $exceptionSubscribed->onKernelResponse($eventResponse);

        static::assertSame($responseMock, $eventResponse->getResponse());
    }
}
