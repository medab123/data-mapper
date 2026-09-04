<?php

declare(strict_types=1);

namespace Medox\DataMapper\ValueMatchers;

use Medox\DataMapper\ColumnMatchers\RelaxedColumnMatcher;
use Medox\DataMapper\Contracts\NormalizerInterface;
use Medox\DataMapper\Contracts\ValueMatcherInterface;
use Medox\DataMapper\Normalizers\FormattingNormalizer;

/**
 * Matches a value against a mapping table, forgiving formatting but not identity.
 *
 * A mapping is a statement about what a source's value MEANS, and " Used" does not mean
 * something different from "Used". With a byte-exact lookup it did: a hand-written
 * `Used => used` silently missed every row a provider happened to send as `USED`, the
 * value reached the target un-normalised, and the field then failed its type cast for
 * the whole file. The miss is invisible at the point it happens, which is what makes it
 * expensive — it surfaces far away, as a cast error nobody can trace back to a table.
 *
 * An exact key always wins, so the relaxed pass runs only when no exact key exists. It
 * therefore cannot change a lookup that already succeeded, only rescue one that failed.
 *
 * Keys that collide once folded are DROPPED rather than letting one shadow the other.
 * Two entries differing only in case are the one case where the author plainly meant
 * them to be distinct — they typed both, in one table — and quietly picking a winner
 * there would be worse than not folding at all. This is the deliberate difference from
 * {@see RelaxedColumnMatcher}, where colliding keys are
 * an accident of the data rather than something anyone wrote, and the first one wins.
 */
final class RelaxedValueMatcher implements ValueMatcherInterface
{
    /**
     * Folded forms of strings already seen.
     *
     * Deliberately NOT a cache of whole folded tables keyed by a signature: two rules
     * whose tables share keys but differ in values ("Used => used" and "Used => new")
     * would collide and one would answer for the other. Deriving a signature that
     * covers values too costs more per row than folding the handful of keys a table
     * actually holds, so the fold is what gets remembered.
     *
     * @var array<string, string>
     */
    private array $folded = [];

    /** Bounded so a pathological configuration cannot turn this into a leak. */
    private const int CACHE_LIMIT = 4096;

    public function __construct(
        private readonly NormalizerInterface $normalizer = new FormattingNormalizer,
    ) {}

    public function matchValue(mixed $value, array $valueMapping): mixed
    {
        if ($valueMapping === []) {
            return $value;
        }

        // Only strings and integers are things a mapping table can key on. A null means
        // "absent", not "matches the empty-string key"; an array is a multi-value
        // extraction the caller should be mapping over element by element; and folding a
        // float or a bool into a key would be inventing a numeric equivalence that
        // belongs to a transformer, not to a lookup.
        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        if (array_key_exists($value, $valueMapping)) {
            return $valueMapping[$value];
        }

        $wanted = $this->fold((string) $value);

        if ($wanted === '') {
            return $value;
        }

        $folded = $this->foldedMapping($valueMapping);

        return array_key_exists($wanted, $folded) ? $folded[$wanted] : $value;
    }

    /**
     * The table re-keyed by folded key, with collisions removed.
     *
     * @param  array<array-key, mixed>  $valueMapping
     * @return array<string, mixed>
     */
    private function foldedMapping(array $valueMapping): array
    {
        $folded = [];
        $collisions = [];

        foreach ($valueMapping as $from => $to) {
            $key = $this->fold((string) $from);

            if ($key === '') {
                continue;
            }

            if (array_key_exists($key, $folded)) {
                $collisions[$key] = true;

                continue;
            }

            $folded[$key] = $to;
        }

        foreach (array_keys($collisions) as $key) {
            unset($folded[$key]);
        }

        return $folded;
    }

    /**
     * The normalizer, remembered.
     *
     * A table's keys recur on every row, so the same few strings fold over and over.
     */
    private function fold(string $value): string
    {
        if (isset($this->folded[$value])) {
            return $this->folded[$value];
        }

        if (count($this->folded) >= self::CACHE_LIMIT) {
            $this->folded = [];
        }

        return $this->folded[$value] = $this->normalizer->normalize($value);
    }
}
