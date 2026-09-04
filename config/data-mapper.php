<?php

declare(strict_types=1);

use Medox\DataMapper\ColumnMatchers\RelaxedColumnMatcher;
use Medox\DataMapper\Normalizers\FormattingNormalizer;
use Medox\DataMapper\ValueMatchers\RelaxedValueMatcher;

return [

    /*
    |--------------------------------------------------------------------------
    | Column matching
    |--------------------------------------------------------------------------
    |
    | Decides which key of a row a configured column name means. A mapping rule
    | names "dealer id"; the source exports "Dealer ID". Whether those are the same
    | column is a policy, so it is yours to pick.
    |
    |   RelaxedColumnMatcher (default) — an exact key always wins; only when none
    |     exists is the name compared with letter case, surrounding whitespace and a
    |     byte-order mark folded away. It can never change a lookup that already
    |     found a value, only rescue one that found nothing.
    |
    |   ExactColumnMatcher — byte-for-byte. "Price" and "price" stay different
    |     columns and a near-miss stays a miss.
    |
    | Any class implementing ColumnMatcherInterface works here, and subclassing
    | RelaxedColumnMatcher to override normalize() is the cheapest way to move the
    | line between formatting and identity for your own sources.
    |
    */

    'column_matcher' => RelaxedColumnMatcher::class,

    /*
    |--------------------------------------------------------------------------
    | Normalizer
    |--------------------------------------------------------------------------
    |
    | Folds a string down to the part that carries its identity. This is the one
    | decision shared by column matching and value mapping: change it here and both
    | move together, which is the point — two answers to "what is formatting" drift
    | apart, and the drift is invisible until something silently fails to match.
    |
    | FormattingNormalizer (default) treats letter case, surrounding whitespace and a
    | byte-order mark as formatting. Nothing else: "stock_number" and "Stock Number"
    | stay different names, because guessing across separators would start matching
    | things nobody wrote.
    |
    | Any class implementing NormalizerInterface works here. It must be a pure
    | function of its argument, because results are cached.
    |
    */

    'normalizer' => FormattingNormalizer::class,

    /*
    |--------------------------------------------------------------------------
    | Value matching
    |--------------------------------------------------------------------------
    |
    | Decides how loosely a source's value may match a key in a rule's value mapping
    | table. The mirror of column matching, with the provenance reversed: there the
    | probe is your column name, here it is the source's value.
    |
    |   RelaxedValueMatcher (default) — an exact key always wins; only when none
    |     exists is the value compared through the normalizer above. Keys that collide
    |     once folded are DROPPED rather than one shadowing the other: two entries
    |     differing only in case were both typed by you, in one table, so you plainly
    |     meant them to be distinct.
    |
    |   ExactValueMatcher — byte-for-byte. "Used" does not answer for "USED".
    |
    */

    'value_matcher' => RelaxedValueMatcher::class,

    /*
    |--------------------------------------------------------------------------
    | Transformer groups
    |--------------------------------------------------------------------------
    |
    | Transformers that belong to your application rather than to this package —
    | anything carrying business meaning — are registered here, keyed by the group
    | they belong to. The package never interprets a group name; it only answers
    | which transformers are in one, so a UI can offer the right subset.
    |
    | The ten shipped transformers always register in the "core" group
    | (Medox\DataMapper\ValueTransformer::GROUP_CORE) and need no entry here.
    |
    |     'groups' => [
    |         'import' => [
    |             App\Import\Transformers\StockNumberTransformer::class,
    |         ],
    |         'export' => [
    |             App\Export\Transformers\TransmissionExpandTransformer::class,
    |         ],
    |     ],
    |
    | Each class must implement Medox\DataMapper\Contracts\TransformerInterface
    | and is resolved through the container, so it may declare its own dependencies.
    |
    */

    'groups' => [
        //
    ],

];
