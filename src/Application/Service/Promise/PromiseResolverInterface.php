<?php

declare(strict_types=1);

namespace App\Application\Service\Promise;

use App\Application\DTO\BatchResult;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use GuzzleHttp\Promise\PromiseInterface;
use Http\Promise\Promise;

/**
 * Handles promise resolution for async operations.
 *
 * Extracted from EditorialOrchestrator to improve testability and single responsibility.
 */
interface PromiseResolverInterface
{
    /**
     * Resolve any array of promises in parallel.
     *
     * Generic method for resolving multiple promises of any type.
     * Returns a BatchResult with fulfilled values and rejected errors.
     *
     * @param array<string, PromiseInterface|Promise> $promises Keyed by identifier
     *
     * @return BatchResult Contains fulfilled values and rejected errors
     */
    public function resolveAll(array $promises): BatchResult;
    /**
     * Resolve an array of multimedia promises into a keyed array.
     *
     * @param array<int, PromiseInterface|Promise> $promises
     *
     * @return array<string, Multimedia> Keyed by multimedia ID
     */
    public function resolveMultimedia(array $promises): array;

    /**
     * Create a callback closure for promise resolution.
     *
     * @param callable $callable The function to wrap
     * @param mixed ...$parameters Additional parameters to pass to the callable
     */
    public function createCallback(callable $callable, mixed ...$parameters): \Closure;

    /**
     * Resolve membership links promise and combine with original links.
     *
     * @param Promise|PromiseInterface|null $promise
     * @param array<int, string> $links Original link URLs
     *
     * @return array<string, mixed> Combined array of link => resolved value
     */
    public function resolveMembershipLinks(Promise|PromiseInterface|null $promise, array $links): array;
}
