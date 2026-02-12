<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\DTO;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class MappingConfigurationData extends Data
{
    public function __construct(
        public array $data,
        /** @var DataCollection<MappingRuleData> */
        public DataCollection $mappingRules,
        public ?array $headers = []
    ) {}
}
