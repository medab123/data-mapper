<?php

declare(strict_types=1);

namespace Medox\DataMapper\Contracts;

use Medox\DataMapper\DTO\DataMappingResultData;
use Medox\DataMapper\DTO\MappingConfigurationData;

interface DataMapperInterface
{
    public function map(MappingConfigurationData $config): DataMappingResultData;
}
