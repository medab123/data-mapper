<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests\Unit;

use Medox\DataMapper\FieldExtractor;
use Medox\DataMapper\Tests\TestCase;
use Medox\DataMapper\Transformers\DateTransformer;
use Medox\DataMapper\Transformers\IntegerTransformer;

class TransformersTest extends TestCase
{
    public function test_integer_transformer_handles_garbage_and_messy_strings(): void
    {
        $int = new IntegerTransformer;

        $this->assertSame(5, $int->transform('5'));
        $this->assertSame(41000, $int->transform('41,000 km'));
        $this->assertSame(0, $int->transform('abc'));
        $this->assertSame(7, $int->transform('abc', null, 7)); // falls back to default
    }

    public function test_date_transformer_returns_default_instead_of_throwing(): void
    {
        $date = new DateTransformer;

        $this->assertNull($date->transform('not-a-date'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $date->transform('2024-01-15'));
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
