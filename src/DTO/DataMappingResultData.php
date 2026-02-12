<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\DTO;

use Spatie\LaravelData\Data;

final class DataMappingResultData extends Data
{
    public function __construct(
        public array $mappedData,
        public array $errors,
        public ?array $filterStats = null,
        public ?array $filterErrors = null,
    ) {}
}
