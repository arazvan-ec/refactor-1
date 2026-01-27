<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Contract;

use App\Application\DataTransformer\DTO\ResolveDataDTO;
use Ec\Editorial\Domain\Model\Body\BodyElement;

/**
 * Contract for body element transformers (standardized version).
 *
 * Implements Strategy pattern for transforming different body element types.
 * This is the preferred interface - new transformers should implement this.
 *
 * Naming follows PSR convention with 'Interface' suffix.
 *
 * @see \App\Application\DataTransformer\BodyElementDataTransformer Legacy interface
 */
interface BodyElementTransformerInterface
{
    /**
     * Write element data to transformer state (fluent interface).
     *
     * Accepts typed ResolveDataDTO for type safety.
     * For legacy compatibility, implementations may also accept arrays.
     */
    public function write(BodyElement $bodyElement, ResolveDataDTO $resolveData): self;

    /**
     * Read transformed output.
     *
     * @return array<string, mixed>
     */
    public function read(): array;

    /**
     * Get the fully qualified class name this transformer handles.
     *
     * @return class-string<BodyElement>
     */
    public function canTransform(): string;
}
