<?php

declare(strict_types=1);

namespace Medox\DataMapper\ColumnMatchers;

use Medox\DataMapper\Contracts\ColumnMatcherInterface;

/**
 * Matches a configured column only when the source spells it byte for byte.
 *
 * This is the strict policy: "Price" and "price" are two different columns, and a
 * mapping naming one does not find the other. Bind it when a source's schema is
 * fixed and a near-miss should stay a miss rather than be quietly resolved.
 *
 * {@see RelaxedColumnMatcher} is the default, and is what most feed-shaped sources
 * want.
 */
final class ExactColumnMatcher implements ColumnMatcherInterface
{
    public function matchKey(array $row, string $column): string|int|null
    {
        return array_key_exists($column, $row) ? $column : null;
    }

    public function matchIndex(array $headers, string $column): ?int
    {
        $index = array_search($column, $headers, true);

        return is_int($index) ? $index : null;
    }
}
