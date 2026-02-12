<?php

declare(strict_types=1);

namespace Elaitech\DataMapper\Contracts;

use Elaitech\DataMapper\DTO\DataMappingResultData;
use Elaitech\DataMapper\DTO\MappingConfigurationData;

interface DataMapperInterface
{
    public function map(MappingConfigurationData $config): DataMappingResultData;
}
