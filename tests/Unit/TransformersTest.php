<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests\Unit;

use Medox\DataMapper\DataMapperService;
use Medox\DataMapper\DTO\MappingConfigurationData;
use Medox\DataMapper\DTO\MappingRuleData;
use Medox\DataMapper\FieldExtractor;
use Medox\DataMapper\Tests\TestCase;
use Medox\DataMapper\Transformers\DateTransformer;
use Medox\DataMapper\Transformers\IntegerTransformer;
use Medox\DataMapper\ValueTransformer;
use Spatie\LaravelData\DataCollection;

class TransformersTest extends TestCase
{
    public function test_integer_transformer_is_a_plain_cast(): void
    {
        $int = new IntegerTransformer;

        $this->assertSame(5, $int->transform('5'));
        $this->assertSame(41000, $int->transform('41000'));
        $this->assertSame(0, $int->transform('abc'));
        $this->assertSame(0, $int->transform(null));

        // The sharp edges, asserted so they are a decision and not a surprise: PHP
        // takes the leading numeric run and discards the rest, so a grouped number
        // loses everything after the separator and a currency symbol loses all of it.
        $this->assertSame(41, $int->transform('41,000 km'));
        $this->assertSame(0, $int->transform('$31,500'));

        // The default is not consulted. Casting is total — it always produces an int —
        // so there is no failure for a default to stand in for.
        $this->assertSame(0, $int->transform('abc', null, 7));
    }

    public function test_date_transformer_returns_null_instead_of_throwing(): void
    {
        $date = new DateTransformer;

        // A malformed date must not cost the whole row: the mapper catches per row, so
        // throwing here discarded every other field beside it.
        $this->assertNull($date->transform('not-a-date'));
        $this->assertNull($date->transform('31/02/2024', 'Y-m-d'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $date->transform('2024-01-15'));
    }

    public function test_date_transformer_returns_the_same_type_on_every_path(): void
    {
        $date = new DateTransformer;

        // The regression this guards: the default came back exactly as configured — a
        // raw string — on the malformed path, while the empty path had it coerced into
        // a date. Same rule, same default, two types.
        $fromEmpty = $date->transform('', null, '2020-01-01');
        $fromGarbage = $date->transform('not-a-date', null, '2020-01-01');

        $this->assertInstanceOf(\DateTimeImmutable::class, $fromEmpty);
        $this->assertInstanceOf(\DateTimeImmutable::class, $fromGarbage);
        $this->assertSame('2020-01-01', $fromGarbage->format('Y-m-d'));
        $this->assertEquals($fromEmpty, $fromGarbage);
    }

    public function test_date_transformer_survives_a_default_that_is_not_a_date(): void
    {
        $date = new DateTransformer;

        // A configuration mistake should not kill a run on its last field.
        $this->assertNull($date->transform('not-a-date', null, 'also-not-a-date'));
        $this->assertNull($date->transform('not-a-date', null, ['an', 'array']));
    }

    public function test_date_transformer_accepts_a_date_it_is_given(): void
    {
        $date = new DateTransformer;
        $already = new \DateTimeImmutable('2024-03-01');

        $this->assertSame($already, $date->transform($already));
        $this->assertSame($already, $date->transform('not-a-date', null, $already));
    }

    public function test_the_mapper_keeps_a_row_whose_date_is_malformed(): void
    {
        $rules = new DataCollection(
            MappingRuleData::class,
            [
                ['source_field' => 'VIN', 'target_field' => 'vin'],
                ['source_field' => 'Sold', 'target_field' => 'sold_at', 'transformation' => 'date'],
            ]
        );

        $result = (new DataMapperService(
            new ValueTransformer,
            new FieldExtractor,
        ))->map(new MappingConfigurationData(
            data: [['VIN' => 'X', 'Sold' => 'garbage']],
            mappingRules: $rules,
            headers: [],
        ));

        // The vehicle survives; only the date it could not read is missing.
        $this->assertCount(1, $result->mappedData);
        $this->assertSame('X', $result->mappedData[0]['vin']);
        $this->assertNull($result->mappedData[0]['sold_at']);

        // NOTE: errors is empty. Keeping the row is right, saying nothing about it is
        // not — that is the reporting gap, asserted here so it is visible rather than
        // assumed to be handled.
        $this->assertSame([], $result->errors);
    }

    public function test_field_extractor_supports_multi_level_wildcards(): void
    {
        $fe = new FieldExtractor;
        $data = [
            'items' => [
                ['variants' => [['sku' => 'A'], ['sku' => 'B']]],
                ['variants' => [['sku' => 'C']]],
            ],
        ];

        $this->assertSame(['A', 'B', 'C'], $fe->extractValueForMapping($data, 'items.*.variants.*.sku'));
    }
}
