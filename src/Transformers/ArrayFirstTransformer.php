<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Transformers;

use Elaitech\DataMapper\Contracts\TransformerInterface;

class ArrayFirstTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'array_first';
    }

    public function getLabel(): string
    {
        return 'Array First';
    }

    public function getDescription(): string
    {
        return 'Get the first element from an array';
    }

    public function transform($value, ?string $format = null, $defaultValue = null)
    {
        return is_array($value) ? ($value[0] ?? $defaultValue) : $value;
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
