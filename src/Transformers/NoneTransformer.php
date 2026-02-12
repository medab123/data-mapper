<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Transformers;

use Elaitech\DataMapper\Contracts\TransformerInterface;

class NoneTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'none';
    }

    public function getLabel(): string
    {
        return 'None';
    }

    public function getDescription(): string
    {
        return 'No transformation applied';
    }

    public function transform($value, ?string $format = null, $defaultValue = null)
    {
        return $value;
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
