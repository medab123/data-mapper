<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests\Unit;

use Medox\DataMapper\DataMapperService;
use Medox\DataMapper\DTO\MappingConfigurationData;
use Medox\DataMapper\DTO\MappingRuleData;
use Medox\DataMapper\FieldExtractor;
use Medox\DataMapper\Tests\TestCase;
use Medox\DataMapper\ValueTransformer;
use Spatie\LaravelData\DataCollection;

class ValueTransformerNullTransformationTest extends TestCase
{
    public function test_a_null_transformation_leaves_the_value_alone(): void
    {
        $valueTransformer = new ValueTransformer;
        $rule = new MappingRuleData(sourceField: 's', targetField: 't');

        $this->assertSame('  Ford  ', $valueTransformer->transform('  Ford  ', $rule));
    }

    public function test_a_null_transformation_matches_the_none_transformer(): void
    {
        $valueTransformer = new ValueTransformer;

        $none = new MappingRuleData(sourceField: 's', targetField: 't', transformation: 'none');
        $null = new MappingRuleData(sourceField: 's', targetField: 't');

        $this->assertSame(
            $valueTransformer->transform('  Ford  ', $none),
            $valueTransformer->transform('  Ford  ', $null)
        );
    }

    public function test_an_unregistered_transformation_still_leaves_the_value_alone(): void
    {
        $valueTransformer = new ValueTransformer;
        $rule = new MappingRuleData(sourceField: 's', targetField: 't', transformation: 'no_such_transformer');

        $this->assertSame('Ford', $valueTransformer->transform('Ford', $rule));
    }

    public function test_a_null_transformation_still_applies_value_mapping_and_defaults(): void
    {
        $valueTransformer = new ValueTransformer;

        $mapped = new MappingRuleData(
            sourceField: 's',
            targetField: 't',
            valueMapping: [['from' => 'Used', 'to' => 'used']],
        );
        $this->assertSame('used', $valueTransformer->transform('USED', $mapped));

        $defaulted = new MappingRuleData(sourceField: 's', targetField: 't', defaultValue: 'unknown');
        $this->assertSame('unknown', $valueTransformer->transform('', $defaulted));
    }

    public function test_the_mapper_runs_a_config_whose_rules_name_no_transformation(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            ['source_field' => 'VIN', 'target_field' => 'vin', 'transformation' => null],
            ['source_field' => 'Make', 'target_field' => 'make'],
            ['source_field' => 'KMS', 'target_field' => 'mileage', 'transformation' => 'int'],
        ]);

        $result = (new DataMapperService(new ValueTransformer, new FieldExtractor))->map(
            new MappingConfigurationData(
                data: [['VIN' => 'X', 'Make' => 'Ford', 'KMS' => '41,000']],
                mappingRules: $rules,
                headers: [],
            )
        );

        $this->assertSame(['vin' => 'X', 'make' => 'Ford', 'mileage' => 41000], $result->mappedData[0]);
        $this->assertSame([], $result->errors);
    }
}
