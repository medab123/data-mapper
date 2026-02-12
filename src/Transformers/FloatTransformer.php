<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Transformers;

use Elaitech\DataMapper\Contracts\TransformerInterface;

class FloatTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'float';
    }

    public function getLabel(): string
    {
        return 'Float';
    }

    public function getDescription(): string
    {
        return 'Convert value to float';
    }

    public function transform($value, ?string $format = null, $defaultValue = null): float
    {
        if ($value === null || $value === '') {
            return $defaultValue ?? 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Try to extract numeric value from string
        if (is_string($value)) {
            $cleaned = preg_replace('/[^\d.-]/', '', $value);
            if (is_numeric($cleaned)) {
                return (float) $cleaned;
            }
        }

        return $defaultValue ?? 0.0;
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
