<?php

declare(strict_types=1);

namespace Elaitech\DataMapper;

use Elaitech\DataMapper\DTO\DataMappingResultData;
use Elaitech\DataMapper\DTO\MappingConfigurationData;
use Spatie\LaravelData\DataCollection;
use Elaitech\DataMapper\Contracts\DataMapperInterface;

final readonly class DataMapperService implements DataMapperInterface
{
    public function __construct(
        private ValueTransformer $valueTransformer,
        private FieldExtractor $fieldExtractor
    ) {}

    public function map(MappingConfigurationData $config): DataMappingResultData
    {
        $mappedData = [];
        $errors = [];

        foreach ($config->data as $rowIndex => $row) {
            try {
                $mappedRow = $this->isAssociativeArray($row)
                    ? $this->mapAssociativeRow($row, $config->mappingRules)
                    : $this->mapIndexedRow($row, $config->headers, $config->mappingRules);

                $mappedData[] = $mappedRow;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowIndex}: ".$e->getMessage();
            }
        }

        return new DataMappingResultData($mappedData, $errors);
    }

    private function isAssociativeArray(array $row): bool
    {
        return array_keys($row) !== range(0, count($row) - 1);
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function mapAssociativeRow(array $row, DataCollection $mappingRules): array
    {
        $mappedRow = [];

        foreach ($mappingRules as $rule) {
            $value = $this->fieldExtractor->extractValueForMapping($row, $rule->sourceField);

            if ($value === null && $rule->isRequired) {
                throw new \InvalidArgumentException("Required field '{$rule->sourceField}' not found in data");
            }

            $mappedRow[$rule->targetField] = $this->valueTransformer->transform($value, $rule);
        }

        return $mappedRow;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function mapIndexedRow(array $row, array $headers, DataCollection $mappingRules): array
    {
        $mappedRow = [];

        foreach ($mappingRules as $rule) {
            $sourceIndex = $this->findHeaderIndex($headers, $rule->sourceField);

            if ($sourceIndex === null) {
                if ($rule->isRequired) {
                    throw new \InvalidArgumentException("Required field '{$rule->sourceField}' not found in headers");
                }

                continue;
            }

            $value = $row[$sourceIndex] ?? null;
            $mappedRow[$rule->targetField] = $this->valueTransformer->transform($value, $rule);
        }

        return $mappedRow;
    }

    private function findHeaderIndex(array $headers, string $fieldName): ?int
    {
        $index = array_search($fieldName, $headers, true);

        return $index !== false ? $index : null;
    }
}
