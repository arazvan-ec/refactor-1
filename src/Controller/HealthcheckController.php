<?php
/**
 * @copyright
 */

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author David Mora Gonzálbez <dmora@ext.elconfidencial.com>
 */
class HealthcheckController extends AbstractController
{
    private string $applicationName;
    private string $environment;

    public function __construct(string $applicationName, string $environment)
    {
        $this->applicationName = $this->normalizeApplicationName($applicationName);
        $this->environment = $environment;
    }

    #[OA\Get(
        path: '/healthcheck_cdn',
        operationId: 'healthCheck',
        description: 'Healthcheck CDN',
        summary: 'Healthcheck CDN',
        tags: ['healthcheck'],
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful petition.',
        content: new OA\JsonContent(
            properties: [],
            example: 'SERVICE OK'
        )
    )]
    #[OA\Response(response: Response::HTTP_INTERNAL_SERVER_ERROR, description: 'Exception.')]
    public function healthCheck(): Response
    {
        $this->iniNewrelic();

        return $this->buildResponse();
    }

    private function buildResponse(): Response
    {
        $response = new Response('SERVICE OK');
        $yesterday = (new \DateTime('NOW', new \DateTimeZone('GMT')))->modify('-1 day');
        $now = new \DateTime();

        return $response->setExpires($yesterday)
            ->setLastModified($now)
            ->setCache([
                'no_store' => true,
                'no_cache' => true,
                'must_revalidate' => true,
            ])
            ->setMaxAge(0);
    }

    private function iniNewrelic(): void
    {
        if (extension_loaded('newrelic')) {
            $this->newrelicSetAppName($this->newrelicBuildAppName());
            $this->newrelicNameTransaction($this->newrelicBuildTransactionName());
        }
    }

    private function newrelicBuildAppName(): string
    {
        return strtoupper($this->environment).' PHP HealthCheck';
    }

    private function newrelicBuildTransactionName(): string
    {
        return $this->applicationName.' healthcheckCdn';
    }

    private function normalizeApplicationName(string $applicationName): string
    {
        return ucfirst(strtolower($applicationName));
    }

    protected function newrelicSetAppName(string $name): void
    {
        newrelic_set_appname($name);
    }

    protected function newrelicNameTransaction(string $name): void
    {
        newrelic_name_transaction($name);
    }
}
