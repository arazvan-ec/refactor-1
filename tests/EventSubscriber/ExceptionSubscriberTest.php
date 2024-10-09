<?php
/**
 * @copyright
 */

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 *
 * @covers \App\EventSubscriber\ExceptionSubscriber
 */
class ExceptionSubscriberTest extends TestCase
{
    /**
     * @test
     */
    public function getSubscribedEventsShouldReturnExpectedArray(): void
    {
        $expected = ['kernel.exception' => [['onKernelException', -128]]];

        $exceptionSubscribed = new ExceptionSubscriber('test');
        static::assertSame($expected, $exceptionSubscribed::getSubscribedEvents());
    }

    /**
     * @test
     */
    public function onKernelExceptionShouldChangeResponseWhenNotInDevMode(): void
    {
        $message = 'This is an error message';
        $statusCode = 409;
        $exception = new \Exception($message, $statusCode);
        $kernelMock = $this->createMock(HttpKernelInterface::class);
        $requestMock = $this->createMock(Request::class);
        $exceptionSubscribed = new ExceptionSubscriber('test');

        $event = new ExceptionEvent($kernelMock, $requestMock, HttpKernelInterface::MAIN_REQUEST, $exception);
        $expected = $event->getResponse();
        $exceptionSubscribed->onKernelException($event);

        /** @var Response $changedResponse */
        $changedResponse = $event->getResponse();

        static::assertNotSame($expected, $changedResponse);
        static::assertSame('{"errors":["'.$message.'"]}', $changedResponse->getContent());
        static::assertSame($statusCode, $changedResponse->getStatusCode());
        static::assertSame('application/json', $changedResponse->headers->get('Content-Type'));
    }

    /**
     * @test
     */
    public function onKernelExceptionShouldChangeResponseWhenNotInDevModeAndIsSymfonyException(): void
    {
        $message = 'This is an error message';
        $statusCode = 409;
        $exception = new HttpException($statusCode, $message);
        $kernelMock = $this->createMock(HttpKernelInterface::class);
        $requestMock = $this->createMock(Request::class);
        $exceptionSubscribed = new ExceptionSubscriber('test');

        $event = new ExceptionEvent($kernelMock, $requestMock, HttpKernelInterface::MAIN_REQUEST, $exception);
        $expected = $event->getResponse();
        $exceptionSubscribed->onKernelException($event);

        /** @var Response $changedResponse */
        $changedResponse = $event->getResponse();

        static::assertNotSame($expected, $changedResponse);
        static::assertSame('{"errors":["'.$message.'"]}', $changedResponse->getContent());
        static::assertSame($statusCode, $changedResponse->getStatusCode());
        static::assertSame('application/json', $changedResponse->headers->get('Content-Type'));
    }
}
