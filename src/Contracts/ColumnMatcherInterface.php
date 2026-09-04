<?php

declare(strict_types=1);

namespace Medox\DataMapper\Contracts;

use Medox\DataMapper\ColumnMatchers\ExactColumnMatcher;
use Medox\DataMapper\ColumnMatchers\RelaxedColumnMatcher;

/**
 * Resolves a configured column name against the row a source actually produced.
 *
 * A mapping rule names a column — "dealer id", "stocknumber". The source exports
 * whatever it exports — "Dealer ID", "StockNumber". Whether those are the same
 * column is a policy decision, not a fact, so it lives behind this contract:
 * a project that wants byte-exact keys binds {@see ExactColumnMatcher},
 * one that wants the common formatting differences forgiven takes the default
 * {@see RelaxedColumnMatcher}, and one with its
 * own rules implements this interface.
 *
 * Both methods answer "which part of this row did the caller mean", and both
 * return null when the row holds nothing that could be meant.
 */
interface ColumnMatcherInterface
{
    /**
     * The row's own key for a configured column.
     *
     * Used for keyed rows — an associative array, a decoded JSON object.
     *
     * @param  array<array-key, mixed>  $row
     */
    public function matchKey(array $row, string $column): string|int|null;

    /**
     * The position of a configured column in a positional header list.
     *
     * Used for rows read by index — a CSV whose first line is its header.
     *
     * @param  array<array-key, mixed>  $headers
     */
    public function matchIndex(array $headers, string $column): ?int;
}
