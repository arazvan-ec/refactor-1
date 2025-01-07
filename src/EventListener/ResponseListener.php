<?php
/**
 * @copyright
 */

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @author David Mora Gonzálbez <dmora@ext.elconfidencial.com>
 */
class ResponseListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $response->headers->set(
            'x-system',
            str_replace('ecsnaapi-service', '', $this->getHostname())
        );
    }

    protected function getHostname(): string
    {
        return gethostname() ?: '';
    }
}
