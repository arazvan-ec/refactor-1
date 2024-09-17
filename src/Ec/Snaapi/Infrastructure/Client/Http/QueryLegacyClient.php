<?php
/**
 * @copyright
 */

namespace App\Ec\Snaapi\Infrastructure\Client\Http;

use Ec\Editorial\Domain\Model\Editorial;
use Ec\Infrastructure\Client\Http\ServiceClient;
use Http\Client\HttpAsyncClient;
use Http\Promise\Promise;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class QueryLegacyClient extends ServiceClient
{
    public function __construct(
        string $hostname,
        ?HttpAsyncClient $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?ResponseFactoryInterface $responseFactory = null,
        CacheInterface $cacheAdapter = null
    ) {
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
     * @param string $editorialIdString
     * @param bool $async
     * @param bool $cached
     * @param int $ttlCache
     * @return Editorial|Promise
     *
     * @throws Throwable
     */
    public function findEditorialById(
        string $editorialIdString,
        bool $async = false,
        bool $cached = false,
        int $ttlCache = 60
    ): Editorial|Promise
    {
        $url = $this->buildUrl("/service/content/{$editorialIdString}");

        $request = $this->createRequest('GET', $url);

        /** @var Promise $promise */
        $promise = $this->execute($request, true, $cached, $ttlCache);

        return $async ? $promise : $promise->wait(true);
    }
}
