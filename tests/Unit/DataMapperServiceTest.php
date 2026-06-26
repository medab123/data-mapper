<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Tests\Unit;

use Elaitech\DataMapper\DataMapperService;
use Elaitech\DataMapper\DTO\MappingConfigurationData;
use Elaitech\DataMapper\DTO\MappingRuleData;
use Elaitech\DataMapper\FieldExtractor;
use Elaitech\DataMapper\Tests\TestCase;
use Elaitech\DataMapper\ValueTransformer;
use Spatie\LaravelData\DataCollection;

class DataMapperServiceTest extends TestCase
{
    private function service(): DataMapperService
    {
        return new DataMapperService(new ValueTransformer, new FieldExtractor);
    }

    public function test_applies_value_mapping_and_int_transformation(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(sourceField: 'Status', targetField: 'condition', valueMapping: [['from' => 'Used', 'to' => 'used']]),
            new MappingRuleData(sourceField: 'KMS', targetField: 'mileage', transformation: 'int'),
        ]);

        $config = new MappingConfigurationData(
            data: [['Status' => 'Used', 'KMS' => '41,000']],
            mappingRules: $rules,
            headers: [],
        );

        $result = $this->service()->map($config);

        $this->assertSame('used', $result->mappedData[0]['condition']);
        $this->assertSame(41000, $result->mappedData[0]['mileage']);
        $this->assertSame([], $result->errors);
    }

    public function test_required_empty_field_is_an_error_and_skips_the_row(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(sourceField: 'vin', targetField: 'vin', isRequired: true),
        ]);

        $config = new MappingConfigurationData(
            data: [['vin' => '']],
            mappingRules: $rules,
            headers: [],
        );

        $result = $this->service()->map($config);

        $this->assertCount(0, $result->mappedData);
        $this->assertNotEmpty($result->errors);
    }

    public function test_empty_row_does_not_crash(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(sourceField: 'a', targetField: 'a'),
        ]);

        $config = new MappingConfigurationData(data: [[]], mappingRules: $rules, headers: []);

        $result = $this->service()->map($config);

        $this->assertCount(1, $result->mappedData);
    }
}
