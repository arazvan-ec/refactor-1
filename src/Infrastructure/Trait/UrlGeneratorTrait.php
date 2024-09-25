<?php

namespace App\Infrastructure\Trait;

use App\Infrastructure\Enum\SitesEnum;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Section\Domain\Model\Section;

trait UrlGeneratorTrait
{
    private string $extension;

    public function extension(): string
    {
        return $this->extension;
    }

    private function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    protected function generateUrl(string $format, string $subdomain, string $siteId, string $urlPath): string
    {
        return sprintf(
            $format,
            $subdomain,
            SitesEnum::getHostnameById($siteId),
            $this->extension,
            trim($urlPath, '/')
        );
    }







}
