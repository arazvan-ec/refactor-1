<?php

declare(strict_types=1);

namespace App\Application\Service\Promise;

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
