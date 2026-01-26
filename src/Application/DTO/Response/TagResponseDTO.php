<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * DTO for tag information in editorial response.
 */
final readonly class TagResponseDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
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
        ];
    }
}
