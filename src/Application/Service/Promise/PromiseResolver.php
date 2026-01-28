<?php

declare(strict_types=1);

namespace App\Application\Service\Promise;

use App\Application\DTO\BatchResult;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Http\Promise\Promise;
use Psr\Log\LoggerInterface;

/**
 * Handles promise resolution for async operations.
 *
 * Extracted from EditorialOrchestrator to improve testability and single responsibility.
 * Uses Guzzle Promises for async HTTP calls to external microservices.
 */
final class PromiseResolver implements PromiseResolverInterface
{
    private const PROMISE_STATE_FULFILLED = 'fulfilled';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function resolveAll(array $promises): BatchResult
    {
        if (empty($promises)) {
            return new BatchResult([], []);
        }

        $settled = Utils::settle($promises)->wait();

        $fulfilled = [];
        $rejected = [];

        foreach ($settled as $key => $result) {
            if (self::PROMISE_STATE_FULFILLED === $result['state']) {
                $fulfilled[$key] = $result['value'];
            } else {
                $reason = $result['reason'] ?? new \RuntimeException('Unknown error');
                $rejected[$key] = $reason instanceof \Throwable
                    ? $reason
                    : new \RuntimeException((string) $reason);

                $this->logger->warning('Promise rejected during batch resolution', [
                    'key' => $key,
                    'reason' => $rejected[$key]->getMessage(),
                ]);
            }
        }

        return new BatchResult($fulfilled, $rejected);
    }

    /**
     * {@inheritDoc}
     */
    public function resolveMultimedia(array $promises): array
    {
        if (empty($promises)) {
            return [];
        }

        $settled = Utils::settle($promises)->wait();

        return $this->extractFulfilledMultimedia($settled);
    }

    /**
     * {@inheritDoc}
     */
    public function createCallback(callable $callable, mixed ...$parameters): \Closure
    {
        return static function ($element) use ($callable, $parameters) {
            return $callable($element, ...$parameters);
        };
    }

    /**
     * {@inheritDoc}
     */
    public function resolveMembershipLinks(Promise|PromiseInterface|null $promise, array $links): array
    {
        if (null === $promise) {
            return [];
        }

        try {
            /** @var array<mixed> $result */
            $result = $promise->wait();
        } catch (\Throwable) {
            return [];
        }

        if (empty($result)) {
            return [];
        }

        return array_combine($links, $result);
    }

    /**
     * Extract fulfilled multimedia from settled promises.
     *
     * @param array<int, array{state: string, value?: Multimedia, reason?: \Throwable}> $settled
     *
     * @return array<string, Multimedia>
     */
    private function extractFulfilledMultimedia(array $settled): array
    {
        $result = [];

        foreach ($settled as $promise) {
            if (self::PROMISE_STATE_FULFILLED === $promise['state']) {
                /** @var Multimedia $multimedia */
                $multimedia = $promise['value'];
                $result[$multimedia->id()] = $multimedia;
            } else {
                $this->logRejectedPromise($promise);
            }
        }

        return $result;
    }

    /**
     * Log information about a rejected promise.
     *
     * @param array{state: string, reason?: \Throwable} $promise
     */
    private function logRejectedPromise(array $promise): void
    {
        $reason = $promise['reason'] ?? null;
        $message = $reason instanceof \Throwable ? $reason->getMessage() : 'Unknown reason';

        $this->logger->warning('Promise rejected during multimedia resolution', [
            'reason' => $message,
        ]);
    }
}
