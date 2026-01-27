<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

/**
 * Architecture validation for EventSubscriber layer.
 *
 * Ensures that EventSubscribers don't inject HTTP clients.
 * EventSubscribers handle cross-cutting concerns (caching, exceptions),
 * not business logic or data fetching.
 *
 * @group architecture
 * @group architecture-subscriber
 */
final class EventSubscriberArchitectureTest extends AbstractArchitectureTest
{
    protected function getForbiddenPatterns(): array
    {
        return self::HTTP_CLIENT_PATTERNS;
    }

    protected function getViolationMessage(): string
    {
        return <<<'MSG'
EventSubscriber classes should NOT inject HTTP clients.
EventSubscribers handle cross-cutting concerns like caching and exception handling.

To fix:
1. Move HTTP-related logic to Orchestrator layer
2. EventSubscribers should only use Logger, Request, Response objects
MSG;
    }

    /**
     * @dataProvider eventSubscriberClassesProvider
     */
    public function test_event_subscribers_do_not_inject_http_clients(string $className): void
    {
        $this->assertNoForbiddenDependencies($className);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function eventSubscriberClassesProvider(): iterable
    {
        $subscriberDir = self::getSrcDir() . '/EventSubscriber';

        foreach (self::findPhpClasses($subscriberDir) as $className) {
            yield $className => [$className];
        }
    }
}
