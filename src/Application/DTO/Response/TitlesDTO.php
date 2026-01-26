<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * DTO for editorial titles.
 */
final readonly class TitlesDTO
{
    public function __construct(
        public string $title,
        public string $preTitle,
        public string $urlTitle,
        public string $mobileTitle,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'preTitle' => $this->preTitle,
            'urlTitle' => $this->urlTitle,
            'mobileTitle' => $this->mobileTitle,
        ];
    }
}
