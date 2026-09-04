<?php

declare(strict_types=1);

namespace Medox\DataMapper\Transformers;

use Medox\DataMapper\Contracts\TransformerInterface;

class DateTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'date';
    }

    public function getLabel(): string
    {
        return 'Date';
    }

    public function getDescription(): string
    {
        return 'Parse date string to DateTimeImmutable object';
    }

    /**
     * A date, or null when neither the value nor the default is one.
     *
     * An unparseable date does not throw. A source sending one malformed date should
     * not cost the whole row, which is what throwing here did: the mapper catches per
     * row, so one bad date discarded every other field beside it.
     *
     * The return type is the point. The default used to come back exactly as
     * configured — a raw string — while the empty-value path had it coerced through
     * this transformer first, so the same rule with the same default produced a
     * DateTimeImmutable for an empty value and a string for a malformed one. A
     * consumer calling ->format() worked on one and died on the other. Now the
     * default is parsed the same way the value is, and the signature makes the
     * inconsistency unrepresentable.
     *
     * A default that is not itself a parseable date yields null rather than an
     * exception, because a run should not die on the last field of the last row over
     * a configuration mistake it could not have detected earlier.
     */
    public function transform($value, ?string $format = null, $defaultValue = null): ?\DateTimeImmutable
    {
        return $this->parse($value, $format) ?? $this->parse($defaultValue, $format);
    }

    /**
     * One date, or null when the input is not one.
     */
    private function parse(mixed $value, ?string $format): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value === null || $value === '' || ! is_scalar($value)) {
            return null;
        }

        try {
            if ($format === null) {
                return new \DateTimeImmutable((string) $value);
            }

            return \DateTimeImmutable::createFromFormat($format, (string) $value) ?: null;
        } catch (\Exception) {
            return null;
        }
    }

    public function requiresFormat(): bool
    {
        return true;
    }
}
