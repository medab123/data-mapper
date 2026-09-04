<?php

declare(strict_types=1);

namespace Medox\DataMapper\ColumnMatchers;

use Medox\DataMapper\Contracts\ColumnMatcherInterface;
use Medox\DataMapper\Contracts\NormalizerInterface;
use Medox\DataMapper\Normalizers\FormattingNormalizer;

/**
 * Matches a configured column against a row, forgiving formatting but not identity.
 *
 * An exact key always wins, so a source carrying both "price" and "Price" keeps them
 * apart for whichever one was configured. The relaxed pass runs ONLY when no exact key
 * exists, which is why it cannot change a lookup that already found a value — it can
 * only rescue one that found nothing. That is what makes turning this on safe.
 *
 * Without it, an exact case-sensitive read is the whole matching policy, and a mapping
 * that is not required is allowed to find nothing: a configuration can name twenty
 * columns, match none of them, and map blank rows while reporting success.
 *
 * What counts as formatting rather than identity: letter case, surrounding whitespace,
 * and a byte-order mark. Nothing else. "stock_number" and "Stock Number" stay different
 * columns, because guessing across separators would start mapping fields nobody asked
 * for. Override {@see self::normalize()} in a subclass if your project wants a different
 * line — that is the intended extension point.
 *
 * When several columns fold to the same normalised name, the first in the row's own
 * order wins.
 */
class RelaxedColumnMatcher implements ColumnMatcherInterface
{
    /**
     * Folded forms of column names already seen.
     *
     * A mapper calls this once per rule per row, and a tabular source repeats the same
     * column names on every one of them, so the same handful of strings fold over and
     * over. Caching the fold is what keeps the relaxed pass cheap; caching the whole
     * row's shape instead was measurably slower, because deriving a key for the row
     * costs more than the scan it was meant to replace.
     *
     * @var array<string, string>
     */
    private array $folded = [];

    /**
     * How many folded names to keep.
     *
     * Bounded so a source with unbounded column names cannot turn this into a leak.
     * A tabular source needs one entry per column and never reaches the limit.
     */
    private const int FOLD_CACHE_LIMIT = 4096;

    public function __construct(
        private readonly NormalizerInterface $normalizer = new FormattingNormalizer,
    ) {}

    public function matchKey(array $row, string $column): string|int|null
    {
        if (array_key_exists($column, $row)) {
            return $column;
        }

        $wanted = $this->fold($column);

        if ($wanted === '') {
            return null;
        }

        foreach ($row as $key => $ignored) {
            if (is_string($key) && $this->fold($key) === $wanted) {
                return $key;
            }
        }

        return null;
    }

    public function matchIndex(array $headers, string $column): ?int
    {
        $exact = array_search($column, $headers, true);

        if (is_int($exact)) {
            return $exact;
        }

        $wanted = $this->fold($column);

        if ($wanted === '') {
            return null;
        }

        foreach ($headers as $index => $header) {
            if (is_int($index) && is_string($header) && $this->fold($header) === $wanted) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Fold a column name down to the part that carries its identity.
     *
     * Prefer configuring `data-mapper.normalizer`, which moves the line for value
     * mapping too. Override here only when column names need a rule of their own.
     * Results are cached, so an override must be a pure function of its argument.
     */
    protected function normalize(string $column): string
    {
        return $this->normalizer->normalize($column);
    }

    /**
     * {@see self::normalize()}, remembered.
     */
    private function fold(string $column): string
    {
        if (isset($this->folded[$column])) {
            return $this->folded[$column];
        }

        if (count($this->folded) >= self::FOLD_CACHE_LIMIT) {
            $this->folded = [];
        }

        return $this->folded[$column] = $this->normalize($column);
    }
}
