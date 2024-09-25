<?php

namespace App\Infrastructure\Trait;

use App\Infrastructure\Enum\SitesEnum;

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

    protected function generateUrl(string $format, string $hostname, string $siteId, string $urlPath): string
    {
        return sprintf(
            $format,
            $this->section->isBlog() ? 'blog' : 'www',
            SitesEnum::getHostnameById($siteId),
            $this->extension,
            trim($urlPath, '/')
        );
    }
}
