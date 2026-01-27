<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of multimedia shots for different sizes.
 *
 * Provides a typed collection of MultimediaShotDTO objects
 * with convenience methods for accessing and converting shots.
 *
 * @implements IteratorAggregate<int, MultimediaShotDTO>
 */
final readonly class MultimediaShotsCollectionDTO implements Countable, IteratorAggregate
{
    /**
     * @param list<MultimediaShotDTO> $shots
     */
    public function __construct(
        private array $shots = [],
    ) {}

    /**
     * @return Traversable<int, MultimediaShotDTO>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->shots);
    }

    public function count(): int
    {
        return count($this->shots);
    }

    public function isEmpty(): bool
    {
        return $this->shots === [];
    }

    /**
     * Get all shots as array.
     *
     * @return list<MultimediaShotDTO>
     */
    public function all(): array
    {
        return $this->shots;
    }

    /**
     * Get shot by size identifier.
     */
    public function getBySize(string $size): ?MultimediaShotDTO
    {
        foreach ($this->shots as $shot) {
            if ($shot->size === $size) {
                return $shot;
            }
        }

        return null;
    }

    /**
     * Check if a shot with given size exists.
     */
    public function hasSize(string $size): bool
    {
        return $this->getBySize($size) !== null;
    }

    /**
     * Convert to array of arrays for JSON serialization.
     *
     * @return list<array{size: string, url: string, width: int, height: int}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn(MultimediaShotDTO $shot): array => $shot->toArray(),
            $this->shots,
        );
    }

    /**
     * Convert to legacy format (size => url map).
     *
     * @return array<string, string>
     */
    public function toLegacyFormat(): array
    {
        $result = [];
        foreach ($this->shots as $shot) {
            $result[$shot->size] = $shot->url;
        }

        return $result;
    }

    /**
     * Create from legacy format (size => url map with dimensions lookup).
     *
     * @param array<string, string> $legacyShots Size => URL mapping
     * @param array<string, array{width: string, height: string}> $sizeDimensions Size => dimensions
     */
    public static function fromLegacyFormat(array $legacyShots, array $sizeDimensions): self
    {
        $shots = [];
        foreach ($legacyShots as $size => $url) {
            $dimensions = $sizeDimensions[$size] ?? ['width' => '0', 'height' => '0'];
            $shots[] = new MultimediaShotDTO(
                size: $size,
                url: $url,
                width: (int) $dimensions['width'],
                height: (int) $dimensions['height'],
            );
        }

        return new self($shots);
    }

    /**
     * Filter shots by minimum width.
     */
    public function filterByMinWidth(int $minWidth): self
    {
        return new self(
            array_values(array_filter(
                $this->shots,
                static fn(MultimediaShotDTO $shot): bool => $shot->width >= $minWidth,
            )),
        );
    }

    /**
     * Get the largest shot by width.
     */
    public function getLargest(): ?MultimediaShotDTO
    {
        if ($this->isEmpty()) {
            return null;
        }

        return array_reduce(
            $this->shots,
            static fn(?MultimediaShotDTO $largest, MultimediaShotDTO $shot): MultimediaShotDTO =>
                $largest === null || $shot->width > $largest->width ? $shot : $largest,
            null,
        );
    }

    /**
     * Get the smallest shot by width.
     */
    public function getSmallest(): ?MultimediaShotDTO
    {
        if ($this->isEmpty()) {
            return null;
        }

        return array_reduce(
            $this->shots,
            static fn(?MultimediaShotDTO $smallest, MultimediaShotDTO $shot): MultimediaShotDTO =>
                $smallest === null || $shot->width < $smallest->width ? $shot : $smallest,
            null,
        );
    }
}
