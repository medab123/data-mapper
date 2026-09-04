<?php

declare(strict_types=1);

namespace Medox\DataMapper;

use Medox\DataMapper\Contracts\GroupedTransformerInterface;
use Medox\DataMapper\Contracts\TransformerInterface;
use Medox\DataMapper\Contracts\ValueMatcherInterface;
use Medox\DataMapper\DTO\MappingRuleData;
use Medox\DataMapper\Transformers\ArrayFirstTransformer;
use Medox\DataMapper\Transformers\ArrayJoinTransformer;
use Medox\DataMapper\Transformers\BooleanTransformer;
use Medox\DataMapper\Transformers\DateTransformer;
use Medox\DataMapper\Transformers\FloatTransformer;
use Medox\DataMapper\Transformers\IntegerTransformer;
use Medox\DataMapper\Transformers\LowerTransformer;
use Medox\DataMapper\Transformers\NoneTransformer;
use Medox\DataMapper\Transformers\TrimTransformer;
use Medox\DataMapper\Transformers\UpperTransformer;
use Medox\DataMapper\ValueMatchers\RelaxedValueMatcher;

final class ValueTransformer
{
    /**
     * The group the ten shipped transformers register in.
     *
     * They are type-level operations — trim, int, date — that mean the same thing
     * wherever a mapping runs, so every caller asks for this group alongside its own.
     */
    public const string GROUP_CORE = 'core';

    /** @var array<string, TransformerInterface> */
    private array $transformers = [];

    /**
     * Group membership, keyed by transformer name.
     *
     * A transformer may belong to several groups; the package never assumes what a
     * group means, it only answers which transformers are in one.
     *
     * @var array<string, array<int, string>>
     */
    private array $groups = [];

    /** @var array<string> Array transformer names that should handle empty values */
    private const array ARRAY_TRANSFORMERS = ['array_join', 'array_first'];

    public function __construct(
        private readonly ValueMatcherInterface $valueMatcher = new RelaxedValueMatcher,
    ) {
        $this->registerBuiltInTransformers();
    }

    /**
     * Register a transformer, optionally into one or more groups.
     *
     * With no group given, a {@see GroupedTransformerInterface} decides for itself
     * and anything else lands in {@see self::GROUP_CORE}. Registering a name that is
     * already taken replaces both the transformer and its group membership.
     */
    public function registerTransformer(TransformerInterface $transformer, string ...$groups): void
    {
        $name = $transformer->getName();

        $this->transformers[$name] = $transformer;
        $this->groups[$name] = $this->normalizeGroups(
            $groups !== [] ? $groups : $this->declaredGroups($transformer)
        );
    }

    /**
     * Register a whole group at once.
     *
     * This is the entry point an application uses for transformers the package has
     * no business knowing about:
     *
     *     $valueTransformer->registerGroup('export', [new TransmissionExpandTransformer]);
     *
     * @param  iterable<TransformerInterface>  $transformers
     */
    public function registerGroup(string $group, iterable $transformers): void
    {
        foreach ($transformers as $transformer) {
            $this->registerTransformer($transformer, $group);
        }
    }

    /**
     * Add already-registered transformers to a further group.
     *
     * Names that are not registered are ignored, so a caller can widen a group
     * without first checking what the package happens to ship.
     */
    public function addToGroup(string $group, string ...$names): void
    {
        foreach ($names as $name) {
            if (! isset($this->transformers[$name]) || in_array($group, $this->groups[$name], true)) {
                continue;
            }

            $this->groups[$name][] = $group;
        }
    }

    /**
     * Every group that currently holds at least one transformer.
     *
     * @return array<int, string>
     */
    public function getGroups(): array
    {
        $groups = [];

        foreach ($this->groups as $names) {
            foreach ($names as $group) {
                $groups[$group] = true;
            }
        }

        return array_keys($groups);
    }

    /**
     * The groups one transformer belongs to.
     *
     * @return array<int, string>
     */
    public function getGroupsFor(string $name): array
    {
        return $this->groups[$name] ?? [];
    }

    public function hasGroup(string $group): bool
    {
        return in_array($group, $this->getGroups(), true);
    }

    /**
     * Get all registered transformers, in every group.
     *
     * @return array<string, TransformerInterface>
     */
    public function getTransformers(): array
    {
        return $this->transformers;
    }

    /**
     * The transformers belonging to any of the given groups.
     *
     * Passing no group returns everything, which is what a caller that does not
     * care about grouping gets.
     *
     * @return array<string, TransformerInterface>
     */
    public function getTransformersInGroups(string ...$groups): array
    {
        if ($groups === []) {
            return $this->transformers;
        }

        $matched = [];

        foreach ($this->transformers as $name => $transformer) {
            if (array_intersect($groups, $this->groups[$name] ?? []) !== []) {
                $matched[$name] = $transformer;
            }
        }

        return $matched;
    }

    /**
     * Transformer names and labels for UI, narrowed to the given groups.
     *
     * A form that offers only the shipped transformers asks for the core group; one
     * that also offers an application's own asks for both:
     *
     *     $valueTransformer->getTransformerOptions(ValueTransformer::GROUP_CORE);
     *     $valueTransformer->getTransformerOptions(ValueTransformer::GROUP_CORE, 'export');
     *
     * @return array<string, string>
     */
    public function getTransformerOptions(string ...$groups): array
    {
        $options = [];

        foreach ($this->getTransformersInGroups(...$groups) as $name => $transformer) {
            $options[$name] = $transformer->getLabel();
        }

        return $options;
    }

    /**
     * Check if a transformer exists, optionally within given groups.
     */
    public function hasTransformer(string $name, string ...$groups): bool
    {
        if (! isset($this->transformers[$name])) {
            return false;
        }

        return $groups === []
            || array_intersect($groups, $this->groups[$name] ?? []) !== [];
    }

    /**
     * Get a specific transformer, optionally requiring it to be in given groups.
     */
    public function getTransformer(string $name, string ...$groups): ?TransformerInterface
    {
        return $this->hasTransformer($name, ...$groups)
            ? $this->transformers[$name]
            : null;
    }

    public function transform($value, MappingRuleData $rule)
    {
        // Cache transformer lookup to avoid duplicate calls. A rule naming no
        // transformation carries null, which means the same as the none transformer:
        // leave the value alone.
        $transformer = $rule->transformation === null
            ? null
            : $this->getTransformer($rule->transformation);
        $isArrayTransformer = $transformer !== null && in_array($rule->transformation, self::ARRAY_TRANSFORMERS, true);

        // Handle empty values (null, empty string, or empty array)
        if ($this->isEmptyValue($value)) {
            // Array transformers handle empty values themselves
            if ($isArrayTransformer) {
                return $transformer->transform($value, $rule->format, $rule->defaultValue);
            }

            // Coerce a *provided* default through the transformer for type
            // consistency (e.g. default '5' + int => 5). A null default stays
            // null so empty optional fields are not turned into 0/0.0/etc.
            if ($transformer !== null && $rule->defaultValue !== null && $rule->defaultValue !== '') {
                return $transformer->transform($rule->defaultValue, $rule->format, $rule->defaultValue);
            }

            return $rule->defaultValue;
        }

        // Apply value mapping if configured
        $value = $this->applyValueMapping($value, $rule->valueMapping);

        // Apply transformation or return value as-is
        $transformedValue = $transformer
            ? $transformer->transform($value, $rule->format, $rule->defaultValue)
            : $value;

        // Use default if transformation resulted in empty string
        return ($transformedValue === '' && $rule->defaultValue !== null)
            ? $rule->defaultValue
            : $transformedValue;
    }

    /**
     * Check if value is considered empty for transformation purposes.
     */
    private function isEmptyValue(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || (is_array($value) && $value === []);
    }

    /**
     * Apply value mapping to a single value or array of values.
     *
     * How loosely a value may match a table's key is the matcher's decision, not this
     * class's. A multi-value extraction is mapped element by element, because a table
     * describes single values.
     *
     * @param  array<array-key, mixed>|null  $valueMapping
     */
    private function applyValueMapping(mixed $value, ?array $valueMapping): mixed
    {
        if ($valueMapping === null || $valueMapping === []) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->valueMatcher->matchValue($item, $valueMapping),
                $value
            );
        }

        return $this->valueMatcher->matchValue($value, $valueMapping);
    }

    /**
     * The groups a transformer asks to be in, or the core group when it has no say.
     *
     * @return array<int, string>
     */
    private function declaredGroups(TransformerInterface $transformer): array
    {
        if (! $transformer instanceof GroupedTransformerInterface) {
            return [self::GROUP_CORE];
        }

        $declared = $transformer->getGroups();

        return $declared === [] ? [self::GROUP_CORE] : $declared;
    }

    /**
     * Trim, drop blanks and de-duplicate group names, falling back to the core group.
     *
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    private function normalizeGroups(array $groups): array
    {
        $normalized = [];

        foreach ($groups as $group) {
            $group = trim($group);

            if ($group !== '' && ! in_array($group, $normalized, true)) {
                $normalized[] = $group;
            }
        }

        return $normalized === [] ? [self::GROUP_CORE] : $normalized;
    }

    private function registerBuiltInTransformers(): void
    {
        $this->registerGroup(self::GROUP_CORE, [
            new NoneTransformer,
            new TrimTransformer,
            new UpperTransformer,
            new LowerTransformer,
            new IntegerTransformer,
            new FloatTransformer,
            new BooleanTransformer,
            new DateTransformer,
            new ArrayFirstTransformer,
            new ArrayJoinTransformer,
        ]);
    }
}
