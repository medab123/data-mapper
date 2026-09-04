<?php

declare(strict_types=1);

namespace Medox\DataMapper\ValueMatchers;

use Medox\DataMapper\Contracts\ValueMatcherInterface;

/**
 * Matches a value only when the source spells it exactly as the table does.
 *
 * "Used" and "USED" are two different values, and a table naming one does not answer for
 * the other. Bind it when a source's vocabulary is fixed and an unlisted spelling should
 * stay unmapped rather than be quietly resolved.
 *
 * {@see RelaxedValueMatcher} is the default.
 */
final class ExactValueMatcher implements ValueMatcherInterface
{
    public function matchValue(mixed $value, array $valueMapping): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        return array_key_exists($value, $valueMapping) ? $valueMapping[$value] : $value;
    }
}
