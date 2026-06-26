<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Tests\Unit;

use Elaitech\DataMapper\DTO\MappingRuleData;
use Elaitech\DataMapper\Tests\TestCase;

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
}
