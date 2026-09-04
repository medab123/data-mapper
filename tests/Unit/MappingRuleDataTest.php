<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests\Unit;

use Medox\DataMapper\DTO\MappingRuleData;
use Medox\DataMapper\Tests\TestCase;

class MappingRuleDataTest extends TestCase
{
    public function test_list_of_from_to_entries_are_normalized(): void
    {
        $rule = new MappingRuleData(
            sourceField: 'Status',
            targetField: 'condition',
            valueMapping: [['from' => 'Used', 'to' => 'used'], ['from' => 'Damaged', 'to' => 'used']],
        );

        $this->assertSame(['Used' => 'used', 'Damaged' => 'used'], $rule->valueMapping);
    }

    public function test_scalar_value_mapping_entries_do_not_throw(): void
    {
        // Regression: array_key_exists('from', $scalar) used to TypeError.
        $rule = new MappingRuleData(
            sourceField: 's',
            targetField: 't',
            valueMapping: ['0' => 'used', '1' => 'new'],
        );

        $this->assertSame(['0' => 'used', '1' => 'new'], $rule->valueMapping);
    }

    public function test_empty_value_mapping_is_a_noop(): void
    {
        $rule = new MappingRuleData(sourceField: 's', targetField: 't', valueMapping: []);

        $this->assertSame([], $rule->valueMapping);
    }

    public function test_a_mapping_names_no_transformation_by_default(): void
    {
        $rule = new MappingRuleData(sourceField: 's', targetField: 't');

        $this->assertNull($rule->transformation);
    }

    public function test_a_config_saved_with_a_null_transformation_hydrates(): void
    {
        // The shape a form produces: an empty string, which ConvertEmptyStringsToNull
        // turns into null before it is stored. A non-nullable string failed here.
        $rule = MappingRuleData::from([
            'source_field' => 'VIN',
            'target_field' => 'vin',
            'transformation' => null,
            'required' => true,
        ]);

        $this->assertNull($rule->transformation);
        $this->assertTrue($rule->isRequired);
    }

    public function test_a_config_that_omits_the_transformation_hydrates(): void
    {
        // What a writer that drops null keys produces.
        $rule = MappingRuleData::from(['source_field' => 'VIN', 'target_field' => 'vin']);

        $this->assertNull($rule->transformation);
    }

    public function test_a_named_transformation_is_kept(): void
    {
        $rule = MappingRuleData::from([
            'source_field' => 'Make',
            'target_field' => 'make',
            'transformation' => 'upper',
        ]);

        $this->assertSame('upper', $rule->transformation);
    }
}
