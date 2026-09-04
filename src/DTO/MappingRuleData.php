<?php

declare(strict_types=1);

namespace Medox\DataMapper\DTO;

use Medox\DataMapper\ValueTransformer;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class MappingRuleData extends Data
{
    /**
     * A mapping that names no transformation carries null.
     *
     * "This mapping asks for no transformation" and "this mapping asks for the
     * transformer called none" are the same instruction, and null says it without a
     * sentinel string every producer has to remember to write. It also matches the
     * shape a form already posts: an empty string, which Laravel's
     * ConvertEmptyStringsToNull turns into null before it is ever stored — so a config
     * saved from a UI hydrates as itself rather than failing on the type.
     *
     * {@see ValueTransformer::transform()} skips the transformer
     * lookup entirely on null, which is what the none transformer did anyway.
     */
    public function __construct(
        public string $sourceField,
        public string $targetField,
        public ?string $transformation = null,
        #[MapInputName('required')]
        public bool $isRequired = false,
        public mixed $defaultValue = null,
        public ?string $format = null,
        public ?array $valueMapping = null // New: for mapping specific values like 0 => "used", 1 => "new"
    ) {
        $this->valueMapping = $this->normalizeValueMapping($valueMapping);
    }

    private function normalizeValueMapping(?array $valueMapping = null): array
    {
        $normalizedValueMapping = [];
        if ($valueMapping) {
            foreach ($valueMapping as $key => $value) {
                // Two accepted shapes: a list of {from,to} maps, or a flat
                // [from => to] map. Guard array_key_exists against scalar entries.
                if (is_array($value) && array_key_exists('from', $value)) {
                    $normalizedValueMapping[$value['from']] = $value['to'] ?? null;
                } else {
                    $normalizedValueMapping[$key] = $value;
                }
            }
        }

        return $normalizedValueMapping;
    }
}
