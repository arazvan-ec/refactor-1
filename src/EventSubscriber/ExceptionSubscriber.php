<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\DomainExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles exceptions and converts them to JSON responses.
 *
 * Provides consistent error handling for the API with:
 * - Domain exceptions with proper HTTP status codes
 * - Structured error responses
 * - Cache headers for error responses
 *
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    use CacheControl;

    private const SMAXAGE = 64000;
    private const MAXAGE = 60;
    private const STALE_WHILE_REVALIDATE = 60;
    private const STALE_IF_ERROR = 259200;

    private const KERNEL_DEV = 'dev';

    public function __construct(
        private readonly string $appEnv,
        private readonly ?LoggerInterface $logger = null,
    ) {
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
        $throwable = $event->getThrowable();

        // Log exceptions based on type
        $this->logException($throwable);

        if ($this->isProductionEnvironment()) {
            $response = $this->createErrorResponse($throwable);

            $this->setHttpCache(
                $response,
                self::SMAXAGE,
                self::MAXAGE,
                self::STALE_WHILE_REVALIDATE,
                self::STALE_IF_ERROR
            );

            $event->setResponse($response);
        }
    }

    private function isProductionEnvironment(): bool
    {
        return self::KERNEL_DEV !== $this->appEnv;
    }

    private function createErrorResponse(\Throwable $throwable): JsonResponse
    {
        if ($throwable instanceof DomainExceptionInterface) {
            return $this->createDomainErrorResponse($throwable);
        }

        return $this->createGenericErrorResponse($throwable);
    }

    private function createDomainErrorResponse(DomainExceptionInterface $exception): JsonResponse
    {
        $data = [
            'errors' => [
                [
                    'code' => $exception->getErrorCode(),
                    'message' => $exception->getUserMessage(),
                ],
            ],
        ];

        return new JsonResponse($data, $exception->getStatusCode());
    }

    private function createGenericErrorResponse(\Throwable $throwable): JsonResponse
    {
        $statusCode = $this->getStatusCode($throwable);

        $data = [
            'errors' => [
                [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $this->isProductionEnvironment()
                        ? 'An unexpected error occurred'
                        : $throwable->getMessage(),
                ],
            ],
        ];

        return new JsonResponse($data, $statusCode);
    }

    private function getStatusCode(\Throwable $throwable): int
    {
        if ($throwable instanceof DomainExceptionInterface) {
            return $throwable->getStatusCode();
        }

        $statusCode = $throwable->getCode();
        if (method_exists($throwable, 'getStatusCode')) {
            $statusCode = $throwable->getStatusCode();
        }

        if ($this->isValidHttpStatusCode($statusCode)) {
            return $statusCode;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function isValidHttpStatusCode(int $statusCode): bool
    {
        return $statusCode >= 100 && $statusCode <= 599;
    }

    private function logException(\Throwable $throwable): void
    {
        if (null === $this->logger) {
            return;
        }

        // Domain exceptions are expected, log at info level
        if ($throwable instanceof DomainExceptionInterface) {
            $this->logger->info('Domain exception occurred', [
                'code' => $throwable->getErrorCode(),
                'message' => $throwable->getMessage(),
            ]);

            return;
        }

        // Unexpected exceptions are errors
        $this->logger->error('Unexpected exception occurred', [
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString(),
        ]);
    }
}
