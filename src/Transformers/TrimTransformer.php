<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Transformers;

use Elaitech\DataMapper\Contracts\TransformerInterface;

class TrimTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'trim';
    }

    public function getLabel(): string
    {
        return 'Trim';
    }

    public function getDescription(): string
    {
        return 'Remove whitespace from the beginning and end of strings';
    }

    public function transform($value, ?string $format = null, $defaultValue = null)
    {
        return is_string($value) ? trim($value) : $value;
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
