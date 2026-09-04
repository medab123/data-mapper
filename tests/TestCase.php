<?php

declare(strict_types=1);

namespace Medox\DataMapper\Tests;

use Medox\DataMapper\DataMapperServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            DataMapperServiceProvider::class,
        ];
    }
}
