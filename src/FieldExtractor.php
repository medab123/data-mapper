<?php

declare(strict_types=1);

namespace Elaitech\DataMapper;

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
        $values = [];

        // Handle wildcard notation like "items.*.name" or "pricing.discounts.*.amount"
        if (str_contains($fieldPath, '.*.')) {
            $parts = explode('.*.', $fieldPath);
            $arrayKey = $parts[0];
            $subKey = $parts[1] ?? null;

            // First, extract the array using dot notation
            $arrayData = $this->extractValue($data, $arrayKey);

            if (is_array($arrayData)) {
                foreach ($arrayData as $item) {
                    if (is_array($item)) {
                        if ($subKey && isset($item[$subKey])) {
                            $values[] = $item[$subKey];
                        } elseif (! $subKey) {
                            $values[] = $item;
                        }
                    }
                }
            }
        } else {
            // Handle single value extraction
            $value = $this->extractValue($data, $fieldPath);
            if ($value !== null) {
                $values[] = $value;
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
