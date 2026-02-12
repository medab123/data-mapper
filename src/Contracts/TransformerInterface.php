<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Contracts;

interface TransformerInterface
{
    /**
     * Get the unique identifier for this transformer
     */
    public function getName(): string;

    /**
     * Get human-readable label for this transformer
     */
    public function getLabel(): string;

    /**
     * Get description of what this transformer does
     */
    public function getDescription(): string;

    /**
     * Transform the given value
     */
    public function transform($value, ?string $format = null, $defaultValue = null);

    /**
     * Check if this transformer requires a format parameter
     */
    public function requiresFormat(): bool;
}
