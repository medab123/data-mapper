<?php

declare(strict_types=1);

namespace Medox\DataMapper\Contracts;

/**
 * Resolves a source's value against the mapping table an author wrote.
 *
 * The same operation as {@see ColumnMatcherInterface} with the provenance reversed:
 * there the probe is the configured name and the candidates are the source's keys;
 * here the probe is the source's value and the candidates are the author's keys.
 *
 * How loosely those may be compared is a policy, not a fact, so it lives behind this
 * contract. What the mapping MEANS — that "Used" should be written as "used" — is the
 * application's data and arrives in $valueMapping; nothing here interprets it.
 */
interface ValueMatcherInterface
{
    /**
     * The value a mapping table says to write, or the original when nothing matches.
     *
     * An unmatched value is returned unchanged rather than nulled: a table is a set of
     * corrections, not an allow-list, and deciding that an unlisted value is invalid is
     * the caller's judgement to make.
     *
     * @param  array<array-key, mixed>  $valueMapping  from => to
     */
    public function matchValue(mixed $value, array $valueMapping): mixed;
}
