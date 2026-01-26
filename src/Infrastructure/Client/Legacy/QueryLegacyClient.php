<?php

declare(strict_types=1);

namespace App\Infrastructure\Client\Legacy;

use Ec\Infrastructure\Client\Http\ServiceClient;
use Http\Client\HttpAsyncClient;
use Http\Promise\Promise;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Client for querying legacy editorial system.
 *
 * Provides access to editorial content and comments from the legacy system.
 * Used as fallback when editorials don't have sourceEditorial data.
 *
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class QueryLegacyClient extends ServiceClient
{
    private string $legacyHostHeader;

    public function __construct(
        string $hostname,
        string $legacyHostHeader,
        ?HttpAsyncClient $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?CacheInterface $cacheAdapter = null,
    ) {
        $this->legacyHostHeader = $legacyHostHeader;

        parent::__construct(
            $hostname,
            $client,
            $requestFactory,
            $responseFactory,
            [],
            [],
            '1',
            $cacheAdapter,
        );
    }

    /**
     * Find editorial by ID from legacy system.
     *
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function findEditorialById(
        string $editorialIdString,
        bool $async = false,
        bool $cached = false,
        int $ttlCache = 60,
    ): array {
        $url = $this->buildUrl("/service/content/{$editorialIdString}/");

        $request = $this->createRequest('GET', $url, [
            'Host' => $this->legacyHostHeader,
        ]);

        /** @var Promise $promise */
        $promise = $this->execute($request, true, $cached, $ttlCache);

        $promise = $promise->then($this->createCallback([$this, 'buildEditorialFromArray'], $request));

        return $async ? $promise : $promise->wait(true); // @phpstan-ignore return.type
    }

    /**
     * Build editorial data from response.
     *
     * @return array<string, mixed>
     */
    protected function buildEditorialFromArray(ResponseInterface $response, RequestInterface $request): array
    {
        /** @var array<string, mixed> $editorialData */
        $editorialData = json_decode($response->getBody()->__toString(), true);

        return $editorialData;
    }

    /**
     * Find comments count by editorial ID.
     *
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function findCommentsByEditorialId(
        string $editorialIdString,
        bool $async = false,
        bool $cached = false,
        int $ttlCache = 60,
    ): array {
        $url = $this->buildUrl("/service/community/comments/editorial/{$editorialIdString}/0/0/");

        $request = $this->createRequest('GET', $url, [
            'Host' => $this->legacyHostHeader,
        ]);

        /** @var Promise $promise */
        $promise = $this->execute($request, true, $cached, $ttlCache);

        $promise = $promise->then($this->createCallback([$this, 'buildEditorialFromArray'], $request));

        return $async ? $promise : $promise->wait(true); // @phpstan-ignore return.type
    }
}
