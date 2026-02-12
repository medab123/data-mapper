<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Transformers;

use Elaitech\DataMapper\Contracts\TransformerInterface;

class LowerTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'lower';
    }

    public function getLabel(): string
    {
        return 'Lowercase';
    }

    public function getDescription(): string
    {
        return 'Convert string to lowercase';
    }

    public function transform($value, ?string $format = null, $defaultValue = null)
    {
        return is_string($value) ? strtolower(trim($value)) : $value;
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
