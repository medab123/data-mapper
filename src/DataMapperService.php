<?php

declare(strict_types=1);

namespace Medox\DataMapper;

use Medox\DataMapper\ColumnMatchers\RelaxedColumnMatcher;
use Medox\DataMapper\Contracts\ColumnMatcherInterface;
use Medox\DataMapper\Contracts\DataMapperInterface;
use Medox\DataMapper\DTO\DataMappingResultData;
use Medox\DataMapper\DTO\MappingConfigurationData;
use Spatie\LaravelData\DataCollection;

final readonly class DataMapperService implements DataMapperInterface
{
    /**
     * The matcher here resolves positional headers; the one inside FieldExtractor
     * resolves keyed rows. Resolve this service from the container and both are the
     * single instance named by `data-mapper.column_matcher`. Construct it by hand and
     * you are responsible for passing the same policy to both, or a source read
     * positionally will match by different rules than one read by key.
     */
    public function __construct(
        private ValueTransformer $valueTransformer,
        private FieldExtractor $fieldExtractor,
        private ColumnMatcherInterface $columnMatcher = new RelaxedColumnMatcher,
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
        // array_is_list() correctly treats [] as a (non-associative) list,
        // avoiding the range(0, -1) edge case that misclassified empty rows.
        return ! array_is_list($row);
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function mapAssociativeRow(array $row, DataCollection $mappingRules): array
    {
        $mappedRow = [];

        foreach ($mappingRules as $rule) {
            $value = $this->fieldExtractor->extractValueForMapping($row, $rule->sourceField);

            if ($rule->isRequired && $this->isEmptyValue($value)) {
                throw new \InvalidArgumentException("Required field '{$rule->sourceField}' is missing or empty");
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

            if ($rule->isRequired && $this->isEmptyValue($value)) {
                throw new \InvalidArgumentException("Required field '{$rule->sourceField}' is missing or empty");
            }

            $mappedRow[$rule->targetField] = $this->valueTransformer->transform($value, $rule);
        }

        return $mappedRow;
    }

    private function findHeaderIndex(array $headers, string $fieldName): ?int
    {
        return $this->columnMatcher->matchIndex($headers, $fieldName);
    }
}
