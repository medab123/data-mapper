<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Transformers;

use Elaitech\DataMapper\Contracts\TransformerInterface;

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
        return (int) $value;
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
