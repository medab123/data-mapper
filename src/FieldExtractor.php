<?php

declare(strict_types=1);

namespace Medox\DataMapper;

final class FieldExtractor
{
    /**
     * Extract value from data using dot notation for nested fields
     */
    public function extractValue(array $data, string $fieldPath): mixed
    {
        // Handle direct field access
        if (! str_contains($fieldPath, '.')) {
            return $data[$fieldPath] ?? null;
        }

        // Handle nested field access with dot notation
        $keys = explode('.', $fieldPath);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Extract values from array using wildcard notation
     */
    public function extractArrayValues(array $data, string $fieldPath): array
    {
        // No wildcard: single value extraction.
        if (! str_contains($fieldPath, '.*.')) {
            $value = $this->extractValue($data, $fieldPath);

            return $value === null ? [] : [$value];
        }

        // Split on the FIRST wildcard only and recurse on the remainder, so
        // multi-level paths like "items.*.variants.*.sku" are supported.
        $pos = strpos($fieldPath, '.*.');
        $arrayKey = substr($fieldPath, 0, $pos);
        $rest = substr($fieldPath, $pos + 3);

        $arrayData = $this->extractValue($data, $arrayKey);

        $values = [];
        if (is_array($arrayData)) {
            foreach ($arrayData as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (str_contains($rest, '.*.')) {
                    foreach ($this->extractArrayValues($item, $rest) as $nested) {
                        $values[] = $nested;
                    }
                } else {
                    $value = $this->extractValue($item, $rest);
                    if ($value !== null) {
                        $values[] = $value;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Extract value with wildcard support for DataMapper
     */
    public function extractValueForMapping(array $data, string $fieldPath): mixed
    {
        // Handle wildcard notation like "images.*.url"
        if (str_contains($fieldPath, '.*.')) {
            $values = $this->extractArrayValues($data, $fieldPath);

            // Return null if no values found, otherwise return the array
            return empty($values) ? null : $values;
        }

        // Handle direct field access
        return $this->extractValue($data, $fieldPath);
    }

    /**
     * Check if a field exists in the data
     */
    public function hasField(array $data, string $fieldPath): bool
    {
        return $this->extractValue($data, $fieldPath) !== null;
    }
}
