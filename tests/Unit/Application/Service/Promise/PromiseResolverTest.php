<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service\Promise;

use App\Application\Service\Promise\PromiseResolver;
use App\Application\Service\Promise\PromiseResolverInterface;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\RejectedPromise;
use Http\Promise\Promise as HttpPromise;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PromiseResolver::class)]
class PromiseResolverTest extends TestCase
{
    private PromiseResolverInterface $promiseResolver;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->promiseResolver = new PromiseResolver($this->logger);
    }

    #[Test]
    public function it_implements_interface(): void
    {
        self::assertInstanceOf(PromiseResolverInterface::class, $this->promiseResolver);
    }

    #[Test]
    public function resolve_multimedia_returns_array_of_multimedia(): void
    {
        $multimedia1 = $this->createMultimediaMock('id-1');
        $multimedia2 = $this->createMultimediaMock('id-2');

        $promises = [
            new FulfilledPromise($multimedia1),
            new FulfilledPromise($multimedia2),
        ];

        $result = $this->promiseResolver->resolveMultimedia($promises);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertArrayHasKey('id-1', $result);
        self::assertArrayHasKey('id-2', $result);
        self::assertSame($multimedia1, $result['id-1']);
        self::assertSame($multimedia2, $result['id-2']);
    }

    #[Test]
    public function resolve_multimedia_handles_failed_promises(): void
    {
        $multimedia = $this->createMultimediaMock('id-1');

        $promises = [
            new FulfilledPromise($multimedia),
            new RejectedPromise(new \Exception('Failed to fetch')),
        ];

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('Promise rejected'));

        $result = $this->promiseResolver->resolveMultimedia($promises);

        self::assertCount(1, $result);
        self::assertArrayHasKey('id-1', $result);
    }

    #[Test]
    public function resolve_multimedia_returns_empty_array_when_all_fail(): void
    {
        $promises = [
            new RejectedPromise(new \Exception('Failed 1')),
            new RejectedPromise(new \Exception('Failed 2')),
        ];

        $this->logger->expects(self::exactly(2))
            ->method('warning');

        $result = $this->promiseResolver->resolveMultimedia($promises);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function resolve_multimedia_returns_empty_array_for_empty_input(): void
    {
        $result = $this->promiseResolver->resolveMultimedia([]);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function create_callback_returns_closure(): void
    {
        $callable = fn (array $data) => array_keys($data);

        $callback = $this->promiseResolver->createCallback($callable);

        self::assertInstanceOf(\Closure::class, $callback);
    }

    #[Test]
    public function create_callback_executes_callable_with_element(): void
    {
        $called = false;
        $receivedElement = null;

        $callable = function ($element) use (&$called, &$receivedElement) {
            $called = true;
            $receivedElement = $element;

            return $element;
        };

        $callback = $this->promiseResolver->createCallback($callable);
        $result = $callback(['test' => 'data']);

        self::assertTrue($called);
        self::assertEquals(['test' => 'data'], $receivedElement);
    }

    #[Test]
    public function create_callback_passes_additional_parameters(): void
    {
        $receivedParams = [];

        $callable = function ($element, $param1, $param2) use (&$receivedParams) {
            $receivedParams = [$param1, $param2];

            return $element;
        };

        $callback = $this->promiseResolver->createCallback($callable, 'extra1', 'extra2');
        $callback(['element']);

        self::assertEquals(['extra1', 'extra2'], $receivedParams);
    }

    #[Test]
    public function resolve_membership_links_returns_combined_array(): void
    {
        $links = ['link1', 'link2', 'link3'];
        $promise = new FulfilledPromise(['resolved1', 'resolved2', 'resolved3']);

        $result = $this->promiseResolver->resolveMembershipLinks($promise, $links);

        self::assertIsArray($result);
        self::assertCount(3, $result);
        self::assertEquals([
            'link1' => 'resolved1',
            'link2' => 'resolved2',
            'link3' => 'resolved3',
        ], $result);
    }

    #[Test]
    public function resolve_membership_links_returns_empty_on_null_promise(): void
    {
        $result = $this->promiseResolver->resolveMembershipLinks(null, ['link1']);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function resolve_membership_links_returns_empty_on_failure(): void
    {
        $promise = new RejectedPromise(new \Exception('Failed'));

        $result = $this->promiseResolver->resolveMembershipLinks($promise, ['link1']);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function resolve_membership_links_returns_empty_when_result_is_empty(): void
    {
        $promise = new FulfilledPromise([]);

        $result = $this->promiseResolver->resolveMembershipLinks($promise, ['link1']);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    private function createMultimediaMock(string $id): Multimedia
    {
        $multimedia = $this->createMock(Multimedia::class);
        $multimedia->method('id')->willReturn($id);

        return $multimedia;
    }
}
