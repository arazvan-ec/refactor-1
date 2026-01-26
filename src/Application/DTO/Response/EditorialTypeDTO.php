<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * DTO for editorial type information.
 */
final readonly class EditorialTypeDTO
{
    public function __construct(
        public string $id,
        public string $name,
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
        ];
    }
}
