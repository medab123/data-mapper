<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests\Unit;

use Medox\DataMapper\Contracts\GroupedTransformerInterface;
use Medox\DataMapper\Contracts\TransformerInterface;
use Medox\DataMapper\DTO\MappingRuleData;
use Medox\DataMapper\Tests\TestCase;
use Medox\DataMapper\ValueTransformer;

class TransformerGroupsTest extends TestCase
{
    public function test_built_in_transformers_are_in_the_core_group(): void
    {
        $valueTransformer = new ValueTransformer;

        $this->assertSame([ValueTransformer::GROUP_CORE], $valueTransformer->getGroups());
        $this->assertSame([ValueTransformer::GROUP_CORE], $valueTransformer->getGroupsFor('int'));
        $this->assertCount(10, $valueTransformer->getTransformersInGroups(ValueTransformer::GROUP_CORE));
    }

    public function test_a_transformer_registers_into_a_named_group(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerTransformer(new FakeTransformer('shout'), 'export');

        $this->assertTrue($valueTransformer->hasGroup('export'));
        $this->assertSame(['export'], $valueTransformer->getGroupsFor('shout'));
        $this->assertArrayHasKey('shout', $valueTransformer->getTransformersInGroups('export'));
        $this->assertArrayNotHasKey('shout', $valueTransformer->getTransformersInGroups(ValueTransformer::GROUP_CORE));
    }

    public function test_a_whole_group_registers_at_once(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerGroup('import', [
            new FakeTransformer('stock_number'),
            new FakeTransformer('dealer_code'),
        ]);

        $this->assertSame(
            ['stock_number', 'dealer_code'],
            array_keys($valueTransformer->getTransformersInGroups('import'))
        );
    }

    public function test_options_are_narrowed_to_the_requested_groups(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerGroup('export', [new FakeTransformer('transmission_expand')]);
        $valueTransformer->registerGroup('import', [new FakeTransformer('stock_number')]);

        $coreOnly = $valueTransformer->getTransformerOptions(ValueTransformer::GROUP_CORE);
        $this->assertArrayHasKey('int', $coreOnly);
        $this->assertArrayNotHasKey('transmission_expand', $coreOnly);
        $this->assertArrayNotHasKey('stock_number', $coreOnly);

        $exportFacing = $valueTransformer->getTransformerOptions(ValueTransformer::GROUP_CORE, 'export');
        $this->assertArrayHasKey('int', $exportFacing);
        $this->assertArrayHasKey('transmission_expand', $exportFacing);
        $this->assertArrayNotHasKey('stock_number', $exportFacing);
    }

    public function test_options_without_a_group_return_everything(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerGroup('export', [new FakeTransformer('transmission_expand')]);

        $options = $valueTransformer->getTransformerOptions();

        $this->assertCount(11, $options);
        $this->assertArrayHasKey('transmission_expand', $options);
    }

    public function test_a_transformer_declares_its_own_groups(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerTransformer(new SelfGroupingTransformer);

        $this->assertSame(['import', 'export'], $valueTransformer->getGroupsFor('vin_clean'));
        $this->assertArrayHasKey('vin_clean', $valueTransformer->getTransformersInGroups('import'));
        $this->assertArrayHasKey('vin_clean', $valueTransformer->getTransformersInGroups('export'));
    }

    public function test_an_explicit_group_overrides_a_declared_one(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerTransformer(new SelfGroupingTransformer, 'export');

        $this->assertSame(['export'], $valueTransformer->getGroupsFor('vin_clean'));
    }

    public function test_a_transformer_can_be_added_to_a_further_group(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->addToGroup('export', 'trim', 'upper', 'does_not_exist');

        $this->assertSame([ValueTransformer::GROUP_CORE, 'export'], $valueTransformer->getGroupsFor('trim'));
        $this->assertSame([], $valueTransformer->getGroupsFor('does_not_exist'));
        $this->assertArrayHasKey('trim', $valueTransformer->getTransformersInGroups('export'));

        // Adding twice does not duplicate membership.
        $valueTransformer->addToGroup('export', 'trim');
        $this->assertSame([ValueTransformer::GROUP_CORE, 'export'], $valueTransformer->getGroupsFor('trim'));
    }

    public function test_lookups_can_require_a_group(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerGroup('export', [new FakeTransformer('transmission_expand')]);

        $this->assertTrue($valueTransformer->hasTransformer('transmission_expand'));
        $this->assertTrue($valueTransformer->hasTransformer('transmission_expand', 'export'));
        $this->assertFalse($valueTransformer->hasTransformer('transmission_expand', ValueTransformer::GROUP_CORE));

        $this->assertNotNull($valueTransformer->getTransformer('transmission_expand', 'export'));
        $this->assertNull($valueTransformer->getTransformer('transmission_expand', ValueTransformer::GROUP_CORE));
        $this->assertNull($valueTransformer->getTransformer('nope'));
    }

    public function test_grouping_does_not_gate_transformation_itself(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerGroup('export', [new FakeTransformer('shout')]);

        $rule = new MappingRuleData(
            sourceField: 'title',
            targetField: 'title',
            transformation: 'shout',
        );

        // Groups describe what a UI should offer, not what a pipeline may run.
        $this->assertSame('HELLO!', $valueTransformer->transform('hello', $rule));
    }

    public function test_blank_group_names_fall_back_to_core(): void
    {
        $valueTransformer = new ValueTransformer;
        $valueTransformer->registerTransformer(new FakeTransformer('shout'), '  ');

        $this->assertSame([ValueTransformer::GROUP_CORE], $valueTransformer->getGroupsFor('shout'));
    }

    public function test_configured_groups_are_registered_by_the_service_provider(): void
    {
        config()->set('data-mapper.groups', [
            'export' => [SelfGroupingTransformer::class],
        ]);

        $valueTransformer = $this->app->make(ValueTransformer::class);

        // The configured group wins over the class's own declaration.
        $this->assertSame(['export'], $valueTransformer->getGroupsFor('vin_clean'));
        $this->assertArrayHasKey('vin_clean', $valueTransformer->getTransformerOptions('export'));
    }

    public function test_a_non_transformer_in_config_fails_at_resolution(): void
    {
        config()->set('data-mapper.groups', [
            'export' => [\stdClass::class],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $this->app->make(ValueTransformer::class);
    }
}

class FakeTransformer implements TransformerInterface
{
    public function __construct(private readonly string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->name));
    }

    public function getDescription(): string
    {
        return 'Test transformer';
    }

    public function transform($value, ?string $format = null, $defaultValue = null)
    {
        return mb_strtoupper((string) $value).'!';
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}

class SelfGroupingTransformer implements GroupedTransformerInterface
{
    public function getGroups(): array
    {
        return ['import', 'export'];
    }

    public function getName(): string
    {
        return 'vin_clean';
    }

    public function getLabel(): string
    {
        return 'Vin clean';
    }

    public function getDescription(): string
    {
        return 'Test transformer that declares its own groups';
    }

    public function transform($value, ?string $format = null, $defaultValue = null)
    {
        return $value;
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
