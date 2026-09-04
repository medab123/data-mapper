<?php

declare(strict_types=1);

namespace Medox\DataMapper\Transformers;

use Medox\DataMapper\Contracts\TransformerInterface;

class IntegerTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'int';
    }

    public function getLabel(): string
    {
        return 'Integer';
    }

    public function getDescription(): string
    {
        return 'Convert value to integer';
    }

    public function transform($value, ?string $format = null, $defaultValue = null): int
    {
        if ($value === null || $value === '') {
            return (int) ($defaultValue ?? 0);
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        // Try to extract an integer from a messy string (e.g. "41,000 km").
        if (is_string($value)) {
            $cleaned = preg_replace('/[^\d-]/', '', $value);
            if (is_numeric($cleaned)) {
                return (int) $cleaned;
            }
        }

        return (int) ($defaultValue ?? 0);
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
