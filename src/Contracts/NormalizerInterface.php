<?php

declare(strict_types=1);

namespace Medox\DataMapper\Contracts;

/**
 * Folds a string down to the part that carries its identity.
 *
 * This is the one decision shared by column matching and value mapping: what counts
 * as formatting, and what counts as a different thing. Keeping it in one place is the
 * point — a project that decides separators are formatting should not have to say so
 * twice and then discover the two answers have drifted apart.
 *
 * Implementations must be pure functions of their argument. Callers cache results.
 */
interface NormalizerInterface
{
    public function normalize(string $value): string;
}
