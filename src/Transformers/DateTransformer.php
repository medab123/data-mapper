<?php

declare(strict_types=1);

namespace Medox\DataMapper\Transformers;

use Medox\DataMapper\Contracts\TransformerInterface;

class DateTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'date';
    }

    public function getLabel(): string
    {
        return 'Date';
    }

    public function getDescription(): string
    {
        return 'Parse date string to DateTimeImmutable object';
    }

    public function transform($value, ?string $format = null, $defaultValue = null): mixed
    {
        if ($value === null || $value === '') {
            return $defaultValue;
        }

        try {
            if ($format === null) {
                return new \DateTimeImmutable((string) $value);
            }

            $date = \DateTimeImmutable::createFromFormat($format, (string) $value);

            return $date ?: $defaultValue;
        } catch (\Exception $e) {
            // Unparseable date — fall back to the configured default instead of throwing.
            return $defaultValue;
        }
    }

    public function requiresFormat(): bool
    {
        return true;
    }
}
