<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests\Unit;

use Medox\DataMapper\ColumnMatchers\ExactColumnMatcher;
use Medox\DataMapper\ColumnMatchers\RelaxedColumnMatcher;
use Medox\DataMapper\Contracts\ColumnMatcherInterface;
use Medox\DataMapper\DataMapperService;
use Medox\DataMapper\DTO\MappingConfigurationData;
use Medox\DataMapper\DTO\MappingRuleData;
use Medox\DataMapper\FieldExtractor;
use Medox\DataMapper\Tests\TestCase;
use Medox\DataMapper\ValueTransformer;
use Spatie\LaravelData\DataCollection;

class ColumnMatchingTest extends TestCase
{
    // ---------------------------------------------------------------- matcher

    public function test_relaxed_matcher_forgives_case_whitespace_and_bom(): void
    {
        $matcher = new RelaxedColumnMatcher;
        $row = ['Dealer ID' => 1, "\u{FEFF}StockNumber" => 'A1', '  Price  ' => 100];

        $this->assertSame('Dealer ID', $matcher->matchKey($row, 'dealer id'));
        $this->assertSame("\u{FEFF}StockNumber", $matcher->matchKey($row, 'stocknumber'));
        $this->assertSame('  Price  ', $matcher->matchKey($row, 'price'));
    }

    public function test_relaxed_matcher_does_not_guess_across_separators(): void
    {
        $matcher = new RelaxedColumnMatcher;

        $this->assertNull($matcher->matchKey(['Stock Number' => 'A1'], 'stock_number'));
        $this->assertNull($matcher->matchKey(['stock_number' => 'A1'], 'Stock Number'));
    }

    public function test_an_exact_key_always_wins_over_a_folded_one(): void
    {
        $matcher = new RelaxedColumnMatcher;
        $row = ['price' => 'lower', 'Price' => 'upper'];

        // Both spellings are present and stay distinct.
        $this->assertSame('price', $matcher->matchKey($row, 'price'));
        $this->assertSame('Price', $matcher->matchKey($row, 'Price'));
    }

    public function test_relaxed_matcher_returns_null_when_nothing_could_be_meant(): void
    {
        $matcher = new RelaxedColumnMatcher;

        $this->assertNull($matcher->matchKey(['vin' => 'X'], 'mileage'));
        $this->assertNull($matcher->matchKey(['vin' => 'X'], '   '));
        $this->assertNull($matcher->matchKey([], 'vin'));
    }

    public function test_relaxed_matcher_ignores_non_string_keys(): void
    {
        $matcher = new RelaxedColumnMatcher;

        $this->assertNull($matcher->matchKey([0 => 'X', 1 => 'Y'], 'vin'));
    }

    public function test_relaxed_matcher_matches_header_positions(): void
    {
        $matcher = new RelaxedColumnMatcher;
        $headers = ["\u{FEFF}VIN", 'Dealer ID', '  Price'];

        $this->assertSame(0, $matcher->matchIndex($headers, 'vin'));
        $this->assertSame(1, $matcher->matchIndex($headers, 'dealer id'));
        $this->assertSame(2, $matcher->matchIndex($headers, 'PRICE'));
        $this->assertNull($matcher->matchIndex($headers, 'mileage'));
        $this->assertNull($matcher->matchIndex($headers, ''));
    }

    public function test_first_column_wins_when_two_fold_to_the_same_name(): void
    {
        $matcher = new RelaxedColumnMatcher;

        // Neither is an exact match for "price", so the first in row order answers.
        $this->assertSame('PRICE', $matcher->matchKey(['PRICE' => 1, 'Price ' => 2], 'price'));
        $this->assertSame(0, $matcher->matchIndex(['PRICE', 'Price '], 'price'));
    }

    public function test_matching_survives_alternating_row_shapes(): void
    {
        $matcher = new RelaxedColumnMatcher;

        // Same matcher instance, different shapes, repeatedly — cached folds must never
        // let one row's columns answer for another's.
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame('Dealer ID', $matcher->matchKey(['Dealer ID' => 1], 'dealer id'));
            $this->assertSame('VIN', $matcher->matchKey(['VIN' => 'X'], 'vin'));
            $this->assertNull($matcher->matchKey(['VIN' => 'X'], 'dealer id'));
        }
    }

    public function test_the_fold_cache_does_not_grow_without_bound(): void
    {
        $matcher = new RelaxedColumnMatcher;

        // Far more distinct column names than the cache limit; results stay correct.
        for ($i = 0; $i < 9000; $i++) {
            $this->assertSame("Col{$i}", $matcher->matchKey(["Col{$i}" => $i], "col{$i}"));
        }

        $folded = (new \ReflectionProperty(RelaxedColumnMatcher::class, 'folded'))->getValue($matcher);
        $this->assertLessThanOrEqual(4096, count($folded));
    }

    public function test_exact_matcher_keeps_a_near_miss_a_miss(): void
    {
        $matcher = new ExactColumnMatcher;

        $this->assertSame('Price', $matcher->matchKey(['Price' => 1], 'Price'));
        $this->assertNull($matcher->matchKey(['Price' => 1], 'price'));
        $this->assertSame(0, $matcher->matchIndex(['Price'], 'Price'));
        $this->assertNull($matcher->matchIndex(['Price'], 'price'));
    }

    public function test_normalize_is_an_extension_point(): void
    {
        $matcher = new class extends RelaxedColumnMatcher
        {
            protected function normalize(string $column): string
            {
                // A project that decides separators are formatting too.
                return str_replace([' ', '_', '-'], '', parent::normalize($column));
            }
        };

        $this->assertSame('Stock Number', $matcher->matchKey(['Stock Number' => 'A1'], 'stock_number'));
    }

    // ------------------------------------------------------------- integration

    public function test_field_extractor_forgives_casing_including_nested_paths(): void
    {
        $extractor = new FieldExtractor;
        $data = [
            'Vehicle' => ['Engine' => ['Cylinders' => 6]],
            'Images' => [['URL' => 'a.jpg'], ['URL' => 'b.jpg']],
        ];

        $this->assertSame(6, $extractor->extractValue($data, 'vehicle.engine.cylinders'));
        $this->assertSame(['a.jpg', 'b.jpg'], $extractor->extractValueForMapping($data, 'images.*.url'));
    }

    public function test_mapper_maps_a_feed_whose_header_casing_differs_from_the_config(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(sourceField: 'vin', targetField: 'vin'),
            new MappingRuleData(sourceField: 'dealer id', targetField: 'dealer_id'),
        ]);

        // Associative rows.
        $result = $this->mapper()->map(new MappingConfigurationData(
            data: [['VIN' => 'X', 'Dealer ID' => 7]],
            mappingRules: $rules,
            headers: [],
        ));

        $this->assertSame(['vin' => 'X', 'dealer_id' => 7], $result->mappedData[0]);
        $this->assertSame([], $result->errors);
    }

    public function test_mapper_matches_positional_headers_whose_casing_differs(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(sourceField: 'vin', targetField: 'vin'),
            new MappingRuleData(sourceField: 'dealer id', targetField: 'dealer_id'),
        ]);

        $result = $this->mapper()->map(new MappingConfigurationData(
            data: [['X', 7]],
            mappingRules: $rules,
            headers: ["\u{FEFF}VIN", 'Dealer ID'],
        ));

        $this->assertSame(['vin' => 'X', 'dealer_id' => 7], $result->mappedData[0]);
        $this->assertSame([], $result->errors);
    }

    public function test_a_required_field_still_fails_when_no_column_could_be_meant(): void
    {
        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(sourceField: 'mileage', targetField: 'mileage', isRequired: true),
        ]);

        $result = $this->mapper()->map(new MappingConfigurationData(
            data: [['VIN' => 'X']],
            mappingRules: $rules,
            headers: [],
        ));

        $this->assertCount(0, $result->mappedData);
        $this->assertNotEmpty($result->errors);
    }

    public function test_the_configured_matcher_is_what_the_container_injects(): void
    {
        config()->set('data-mapper.column_matcher', ExactColumnMatcher::class);

        $this->assertInstanceOf(ExactColumnMatcher::class, $this->app->make(ColumnMatcherInterface::class));

        $rules = new DataCollection(MappingRuleData::class, [
            new MappingRuleData(sourceField: 'vin', targetField: 'vin'),
        ]);

        $result = $this->app->make(DataMapperService::class)->map(new MappingConfigurationData(
            data: [['VIN' => 'X']],
            mappingRules: $rules,
            headers: [],
        ));

        // Strict policy: "VIN" is not "vin", so nothing is found.
        $this->assertNull($result->mappedData[0]['vin']);
    }

    public function test_the_default_matcher_is_relaxed(): void
    {
        $this->assertInstanceOf(RelaxedColumnMatcher::class, $this->app->make(ColumnMatcherInterface::class));
    }

    public function test_a_non_matcher_in_config_fails_at_resolution(): void
    {
        config()->set('data-mapper.column_matcher', \stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $this->app->make(ColumnMatcherInterface::class);
    }

    private function mapper(): DataMapperService
    {
        return new DataMapperService(new ValueTransformer, new FieldExtractor);
    }
}
