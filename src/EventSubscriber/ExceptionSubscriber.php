<?php

/**
 * @copyright
 */

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    private const KERNEL_DEV = 'dev';
    private string $appEnv;

    public function __construct(string $appEnv)
    {
        $this->appEnv = $appEnv;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onKernelException', -128],
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (self::KERNEL_DEV !== $this->appEnv) {
            $throwable = $event->getThrowable();
            $message = [
                'errors' => [$throwable->getMessage()],
            ];

            $response = new JsonResponse($message, $this->getStatusCode($throwable));

            $event->setResponse($response);
        }
    }

    private function getStatusCode(\Throwable $throwable): int
    {
        $statusCode = $throwable->getCode();
        if (method_exists($throwable, 'getStatusCode')) {
            $statusCode = $throwable->getStatusCode();
        }

        return $statusCode ?: Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
