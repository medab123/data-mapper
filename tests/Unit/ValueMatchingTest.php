<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests\Unit;

use Medox\DataMapper\Contracts\NormalizerInterface;
use Medox\DataMapper\Contracts\ValueMatcherInterface;
use Medox\DataMapper\DataMapperService;
use Medox\DataMapper\DTO\MappingConfigurationData;
use Medox\DataMapper\DTO\MappingRuleData;
use Medox\DataMapper\FieldExtractor;
use Medox\DataMapper\Normalizers\FormattingNormalizer;
use Medox\DataMapper\Tests\TestCase;
use Medox\DataMapper\ValueMatchers\ExactValueMatcher;
use Medox\DataMapper\ValueMatchers\RelaxedValueMatcher;
use Medox\DataMapper\ValueTransformer;
use Spatie\LaravelData\DataCollection;

class ValueMatchingTest extends TestCase
{
    // ----------------------------------------------------------------- matcher

    public function test_relaxed_matcher_forgives_case_whitespace_and_bom(): void
    {
        $matcher = new RelaxedValueMatcher;
        $map = ['Used' => 'used', 'New' => 'new'];

        $this->assertSame('used', $matcher->matchValue('USED', $map));
        $this->assertSame('used', $matcher->matchValue('  used  ', $map));
        $this->assertSame('used', $matcher->matchValue("\u{FEFF}Used", $map));
        $this->assertSame('new', $matcher->matchValue('NEW', $map));
    }

    public function test_an_exact_key_always_wins_over_a_folded_one(): void
    {
        $matcher = new RelaxedValueMatcher;
        // 'Used' and 'used' fold together, so the folded index drops BOTH. An exact
        // key must still answer for itself.
        $map = ['Used' => 'second-hand', 'used' => 'pre-owned'];

        $this->assertSame('second-hand', $matcher->matchValue('Used', $map));
        $this->assertSame('pre-owned', $matcher->matchValue('used', $map));
    }

    public function test_colliding_keys_are_dropped_rather_than_shadowed(): void
    {
        $matcher = new RelaxedValueMatcher;
        $map = ['Used' => 'second-hand', 'USED' => 'pre-owned'];

        // The author typed both, so they meant them apart. A spelling matching neither
        // exactly gets no answer at all rather than an arbitrary winner.
        $this->assertSame('uSeD', $matcher->matchValue('uSeD', $map));

        // Non-colliding entries in the same table still fold normally.
        $this->assertSame('new', $matcher->matchValue('NEW', $map + ['New' => 'new']));
    }

    public function test_a_value_matching_nothing_passes_through_unchanged(): void
    {
        $matcher = new RelaxedValueMatcher;

        $this->assertSame('Salvage', $matcher->matchValue('Salvage', ['Used' => 'used']));
        $this->assertSame('Used', $matcher->matchValue('Used', []));
    }

    public function test_only_strings_and_integers_are_mapped(): void
    {
        $matcher = new RelaxedValueMatcher;
        $map = ['Used' => 'used', '1' => 'new'];

        // Integer keys work — a table keyed 0/1 is a normal shape.
        $this->assertSame('new', $matcher->matchValue(1, $map));

        // Everything else is returned untouched rather than coerced into a key.
        $this->assertNull($matcher->matchValue(null, $map));
        $this->assertSame([1, 2], $matcher->matchValue([1, 2], $map));
        $this->assertSame(1.0, $matcher->matchValue(1.0, $map));
        $this->assertTrue($matcher->matchValue(true, $map));
    }

    public function test_a_blank_value_is_not_folded_into_a_key(): void
    {
        $matcher = new RelaxedValueMatcher;

        $this->assertSame('   ', $matcher->matchValue('   ', ['' => 'blank', 'Used' => 'used']));
    }

    public function test_a_mapping_may_map_to_null(): void
    {
        $matcher = new RelaxedValueMatcher;

        // Distinguishing "mapped to null" from "not found" is why the lookup uses
        // array_key_exists rather than ??.
        $this->assertNull($matcher->matchValue('UNKNOWN', ['Unknown' => null]));
    }

    public function test_the_cache_serves_different_tables_correctly(): void
    {
        $matcher = new RelaxedValueMatcher;

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame('used', $matcher->matchValue('USED', ['Used' => 'used']));
            $this->assertSame('new', $matcher->matchValue('USED', ['Used' => 'new']));
            $this->assertSame('USED', $matcher->matchValue('USED', ['Salvage' => 'salvage']));
        }
    }

    public function test_the_cache_does_not_grow_without_bound(): void
    {
        $matcher = new RelaxedValueMatcher;

        for ($i = 0; $i < 9000; $i++) {
            $this->assertSame("v{$i}", $matcher->matchValue("K{$i}", ["k{$i}" => "v{$i}"]));
        }

        $folded = (new \ReflectionProperty(RelaxedValueMatcher::class, 'folded'))->getValue($matcher);
        $this->assertLessThanOrEqual(4096, count($folded));
    }

    public function test_exact_matcher_keeps_a_near_miss_a_miss(): void
    {
        $matcher = new ExactValueMatcher;

        $this->assertSame('used', $matcher->matchValue('Used', ['Used' => 'used']));
        $this->assertSame('USED', $matcher->matchValue('USED', ['Used' => 'used']));
        $this->assertNull($matcher->matchValue(null, ['Used' => 'used']));
    }

    // -------------------------------------------------------------- normalizer

    public function test_the_normalizer_is_shared_by_both_matchers(): void
    {
        $normalizer = new class implements NormalizerInterface
        {
            public function normalize(string $value): string
            {
                return str_replace([' ', '_'], '', mb_strtolower(trim($value)));
            }
        };

        config()->set('data-mapper.normalizer', $normalizer::class);

        // One decision, applied to column names AND to mapped values.
        $extractor = $this->app->make(FieldExtractor::class);
        $this->assertSame('A1', $extractor->extractValue(['Stock Number' => 'A1'], 'stock_number'));

        $matcher = $this->app->make(ValueMatcherInterface::class);
        $this->assertSame('four wheel drive', $matcher->matchValue('Four_Wheel', ['four wheel' => 'four wheel drive']));
    }

    public function test_formatting_normalizer_folds_only_formatting(): void
    {
        $normalizer = new FormattingNormalizer;

        $this->assertSame('used', $normalizer->normalize("  \u{FEFF}USED \n"));
        $this->assertSame('stock_number', $normalizer->normalize('Stock_Number'));
        $this->assertNotSame($normalizer->normalize('stock number'), $normalizer->normalize('stock_number'));
    }

    // ------------------------------------------------------------- integration

    public function test_the_mapper_maps_a_value_whose_casing_differs_from_the_table(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(
                sourceField: 'Status',
                targetField: 'condition',
                valueMapping: [['from' => 'Used', 'to' => 'used']],
            ),
        ]);

        $result = $this->mapper()->map(new MappingConfigurationData(
            data: [['Status' => 'USED'], ['Status' => ' used '], ['Status' => 'Salvage']],
            mappingRules: $rules,
            headers: [],
        ));

        $this->assertSame('used', $result->mappedData[0]['condition']);
        $this->assertSame('used', $result->mappedData[1]['condition']);
        $this->assertSame('Salvage', $result->mappedData[2]['condition']);
    }

    public function test_multi_value_extractions_are_mapped_element_by_element(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(
                sourceField: 'options.*.name',
                targetField: 'options',
                valueMapping: [['from' => 'Sunroof', 'to' => 'sunroof']],
            ),
        ]);

        $result = $this->mapper()->map(new MappingConfigurationData(
            data: [['options' => [['name' => 'SUNROOF'], ['name' => 'Leather']]]],
            mappingRules: $rules,
            headers: [],
        ));

        $this->assertSame(['sunroof', 'Leather'], $result->mappedData[0]['options']);
    }

    public function test_value_mapping_still_runs_before_the_transformer(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(
                sourceField: 'Status',
                targetField: 'condition',
                transformation: 'upper',
                valueMapping: [['from' => 'Used', 'to' => 'second-hand']],
            ),
        ]);

        $result = $this->mapper()->map(new MappingConfigurationData(
            data: [['Status' => 'USED']],
            mappingRules: $rules,
            headers: [],
        ));

        $this->assertSame('SECOND-HAND', $result->mappedData[0]['condition']);
    }

    public function test_the_configured_matcher_is_what_the_container_injects(): void
    {
        config()->set('data-mapper.value_matcher', ExactValueMatcher::class);

        $this->assertInstanceOf(ExactValueMatcher::class, $this->app->make(ValueMatcherInterface::class));

        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(
                sourceField: 'Status',
                targetField: 'condition',
                valueMapping: [['from' => 'Used', 'to' => 'used']],
            ),
        ]);

        $result = $this->app->make(DataMapperService::class)->map(new MappingConfigurationData(
            data: [['Status' => 'USED']],
            mappingRules: $rules,
            headers: [],
        ));

        $this->assertSame('USED', $result->mappedData[0]['condition']);
    }

    public function test_the_defaults_are_relaxed(): void
    {
        $this->assertInstanceOf(RelaxedValueMatcher::class, $this->app->make(ValueMatcherInterface::class));
        $this->assertInstanceOf(FormattingNormalizer::class, $this->app->make(NormalizerInterface::class));
    }

    public function test_a_non_matcher_in_config_fails_at_resolution(): void
    {
        config()->set('data-mapper.value_matcher', \stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $this->app->make(ValueMatcherInterface::class);
    }

    public function test_a_non_normalizer_in_config_fails_at_resolution(): void
    {
        config()->set('data-mapper.normalizer', \stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $this->app->make(NormalizerInterface::class);
    }

    private function mapper(): DataMapperService
    {
        return new DataMapperService(new ValueTransformer, new FieldExtractor);
    }
}
