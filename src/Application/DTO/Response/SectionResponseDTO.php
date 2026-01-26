<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * DTO for section information in editorial response.
 */
final readonly class SectionResponseDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
        public string $encodeName,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'encodeName' => $this->encodeName,
        ];
    }
}
