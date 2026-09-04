<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests\Unit;

use Medox\DataMapper\FieldExtractor;
use Medox\DataMapper\Tests\TestCase;
use Medox\DataMapper\Transformers\DateTransformer;
use Medox\DataMapper\Transformers\IntegerTransformer;

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
