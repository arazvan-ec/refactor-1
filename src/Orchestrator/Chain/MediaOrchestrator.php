<?php

namespace App\Orchestrator\Chain\Opening;

use App\Application\DataTransformer\Apps\Media\MediaDataTransformerHandler;
use App\Orchestrator\Chain\Orchestrator;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\Multimedia\Multimedia;
use Ec\Editorial\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient as QueryMultimediaOpeningClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class MediaOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryMultimediaOpeningClient $queryMultimediaOpeningClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function canOrchestrate(): string
    {
        return 'media';
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(Request $request): array
    {
        $editorial = $request->attributes->get('editorial');

        if (!$editorial instanceof Editorial) {
            return [];
        }

        return $this->processOpeningMedia($editorial) ?? [];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function processOpeningMedia(Editorial $editorial): ?array
    {
        /** @var NewsBase $editorial */
        $opening = $editorial->opening();

        if (empty($opening->multimediaId())) {
            return $this->handleMetaImage($editorial);
        }

        return $this->handleOpeningMultimedia($opening->multimediaId(), $editorial);
    }

    /**
     * @return ?array<string, mixed>
     */
    private function handleOpeningMultimedia(string $multimediaId, Editorial $editorial): ?array
    {
        try {
            /** @var Multimedia $multimedia */
            $multimedia = $this->queryMultimediaClient->findMultimediaById($multimediaId);

            $resolveData = [$multimediaId => ['opening' => $multimedia]];

            if ($multimedia instanceof MultimediaPhoto) {
                $resource = $this->queryMultimediaClient->findPhotoById($multimedia->resourceId());
                $resolveData[$multimediaId]['resource'] = $resource;
            }

            /** @var NewsBase $editorial */
            return $resolveData;
        } catch (\Throwable $throwable) {
            $this->logger->error('Error handling opening multimedia', [
                'multimediaId' => $multimediaId,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    private function handleMetaImage(Editorial $editorial): ?array
    {
        if (empty($editorial->metaImage())) {
            return null;
        }

        try {
            /** @var Multimedia $multimedia */
            $multimedia = $this->queryMultimediaClient->findMultimediaById($editorial->metaImage());

            // APLICA STRATEGIA POR TIPO DE MEDIA
            // Photo, widget, video, ....
            if (!$multimedia instanceof MultimediaPhoto) {
                return null;
            }

            $resource = $this->queryMultimediaClient->findPhotoById($multimedia->resourceId());

            return [
                $editorial->metaImage() => [
                    'opening' => $multimedia,
                    'resource' => $resource,
                ],
            ];
        } catch (\Throwable $throwable) {
            $this->logger->error('Error handling meta image', [
                'metaImage' => $editorial->metaImage(),
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
