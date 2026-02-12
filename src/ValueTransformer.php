<?php

declare(strict_types=1);

namespace Elaitech\DataMapper;

use Elaitech\DataMapper\Contracts\TransformerInterface;
use Elaitech\DataMapper\DTO\MappingRuleData;

final class ValueTransformer
{
    /** @var array<string, TransformerInterface> */
    private array $transformers = [];

    /** @var array<string> Array transformer names that should handle empty values */
    private const array ARRAY_TRANSFORMERS = ['array_join', 'array_first'];

    public function __construct()
    {
        $this->registerBuiltInTransformers();
    }

    /**
     * Register a new transformer
     */
    public function registerTransformer(TransformerInterface $transformer): void
    {
        $this->transformers[$transformer->getName()] = $transformer;
    }

    /**
     * Get all registered transformers
     *
     * @return array<string, TransformerInterface>
     */
    public function getTransformers(): array
    {
        return $this->transformers;
    }

    /**
     * Get transformer names and labels for UI
     *
     * @return array<string, string>
     */
    public function getTransformerOptions(): array
    {
        $options = [];
        foreach ($this->transformers as $name => $transformer) {
            $options[$name] = $transformer->getLabel();
        }

        return $options;
    }

    /**
     * Check if a transformer exists
     */
    public function hasTransformer(string $name): bool
    {
        return isset($this->transformers[$name]);
    }

    /**
     * Get a specific transformer
     */
    public function getTransformer(string $name): ?TransformerInterface
    {
        return $this->transformers[$name] ?? null;
    }

    public function transform($value, MappingRuleData $rule)
    {
        // Cache transformer lookup to avoid duplicate calls
        $transformer = $this->getTransformer($rule->transformation);
        $isArrayTransformer = $transformer !== null && in_array($rule->transformation, self::ARRAY_TRANSFORMERS, true);

        // Handle empty values (null, empty string, or empty array)
        if ($this->isEmptyValue($value)) {
            // Array transformers handle empty values themselves
            if ($isArrayTransformer) {
                return $transformer->transform($value, $rule->format, $rule->defaultValue);
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
     */
    private function applyValueMapping(mixed $value, ?array $valueMapping): mixed
    {
        if ($valueMapping === null) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(
                fn ($item) => $valueMapping[$item] ?? $item,
                $value
            );
        }

        return $valueMapping[$value] ?? $value;
    }

    private function registerBuiltInTransformers(): void
    {
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\NoneTransformer);
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\TrimTransformer);
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\UpperTransformer);
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\LowerTransformer);
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\IntegerTransformer);
        $this->registerTransformer(new Transformers\FloatTransformer);
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\BooleanTransformer);
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\DateTransformer);
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\ArrayFirstTransformer);
        $this->registerTransformer(new \Elaitech\DataMapper\Transformers\ArrayJoinTransformer);
    }
}
