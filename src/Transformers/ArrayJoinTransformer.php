<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Transformers;

use Elaitech\DataMapper\Contracts\TransformerInterface;

class ArrayJoinTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'array_join';
    }

    public function getLabel(): string
    {
        return 'Array Join';
    }

    public function getDescription(): string
    {
        return 'Join array elements into a string using a separator';
    }

    public function transform($value, ?string $format = null, $defaultValue = null)
    {
        return is_array($value) ? implode($format ?? ',', $value) : $value;
    }

    public function requiresFormat(): bool
    {
        return true;
    }
}
