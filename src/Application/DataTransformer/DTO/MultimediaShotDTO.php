<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

/**
 * Represents a single multimedia shot with its URL and dimensions.
 *
 * Used by MultimediaShotGenerator to return typed shot data
 * instead of raw array<string, string>.
 */
final readonly class MultimediaShotDTO
{
    /**
     * @param string $size Size identifier (e.g., '1440w', '996w', 'lo-res')
     * @param string $url Generated Thumbor URL
     * @param int $width Image width in pixels
     * @param int $height Image height in pixels
     */
    public function __construct(
        public string $size,
        public string $url,
        public int $width,
        public int $height,
    ) {}

    /**
     * @return array{size: string, url: string, width: int, height: int}
     */
    public function toArray(): array
    {
        return [
            'size' => $this->size,
            'url' => $this->url,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * Get aspect ratio as a string.
     */
    public function getAspectRatio(): string
    {
        $gcd = $this->gcd($this->width, $this->height);

        return sprintf('%d:%d', $this->width / $gcd, $this->height / $gcd);
    }

    /**
     * Calculate greatest common divisor.
     */
    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
