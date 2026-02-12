<?php

declare(strict_types=1);

namespace Elaitech\DataMapper;

use Elaitech\DataMapper\Contracts\DataMapperInterface;
use Illuminate\Support\ServiceProvider;

final class DataMapperServiceProvider extends ServiceProvider
{
    /**
     * The container bindings for this package.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DataMapperInterface::class => DataMapperService::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ValueTransformer::class, static fn (): ValueTransformer => new ValueTransformer());
        $this->app->singleton(FieldExtractor::class, static fn (): FieldExtractor => new FieldExtractor());
    }
}

