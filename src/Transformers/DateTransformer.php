<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Transformers;

use Elaitech\DataMapper\Contracts\TransformerInterface;

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

    /**
     * @throws \DateMalformedStringException
     */
    public function transform($value, ?string $format = null, $defaultValue = null): ?\DateTimeImmutable
    {
        if ($format === null) {
            return new \DateTimeImmutable((string) $value);
        }

        $date = \DateTimeImmutable::createFromFormat($format, (string) $value);

        return $date ?: null;
    }

    public function requiresFormat(): bool
    {
        return true;
    }
}
