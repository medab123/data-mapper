<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\DTO;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class MappingRuleData extends Data
{
    public function __construct(
        public string $sourceField,
        public string $targetField,
        public string $transformation = 'none',
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
                if (array_key_exists('from', $value)) {
                    $normalizedValueMapping[$value['from']] = $value['to'];
                } else {
                    $normalizedValueMapping[$key] = $value;
                }
            }

        }

        return $normalizedValueMapping;
    }
}
