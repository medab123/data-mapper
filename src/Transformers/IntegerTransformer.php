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

    /**
     * A plain cast, and deliberately nothing more.
     *
     * PHP's own rules apply: a leading numeric run is taken and the rest discarded,
     * so "41,000" is 41 and "$31,500" is 0. That is a sharp edge, and it is the
     * intended one — this transformer says "cast to int", not "work out what number
     * the source meant". Guessing at that here means guessing what a comma is for,
     * and a comma groups thousands in one locale and marks a decimal in another.
     *
     * A source that writes numbers with separators or symbols wants a format-aware
     * transformer instead, where the convention is stated rather than assumed.
     */
    public function transform($value, ?string $format = null, $defaultValue = null): int
    {
        return (int) $value;
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
