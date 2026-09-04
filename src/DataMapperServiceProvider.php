<?php

declare(strict_types=1);

namespace Medox\DataMapper;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Medox\DataMapper\Contracts\DataMapperInterface;
use Medox\DataMapper\Contracts\TransformerInterface;

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
        $this->mergeConfigFrom(__DIR__.'/../config/data-mapper.php', 'data-mapper');

        $this->app->singleton(ValueTransformer::class, function (Application $app): ValueTransformer {
            $valueTransformer = new ValueTransformer;

            $this->registerConfiguredGroups($app, $valueTransformer);

            return $valueTransformer;
        });

        $this->app->singleton(FieldExtractor::class, static fn (): FieldExtractor => new FieldExtractor);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/data-mapper.php' => $this->app->configPath('data-mapper.php'),
            ], 'data-mapper-config');
        }
    }

    /**
     * Register the application's own transformers into the groups it declared.
     *
     * Resolved through the container so a transformer may take dependencies, and
     * validated on the way in: a class that is not a transformer is a configuration
     * mistake worth failing on at boot rather than a mapping that silently does
     * nothing halfway through a feed.
     */
    private function registerConfiguredGroups(Application $app, ValueTransformer $valueTransformer): void
    {
        /** @var array<string, array<int, class-string>> $groups */
        $groups = $app['config']->get('data-mapper.groups', []);

        foreach ($groups as $group => $transformers) {
            foreach ($transformers as $transformer) {
                $resolved = is_string($transformer) ? $app->make($transformer) : $transformer;

                if (! $resolved instanceof TransformerInterface) {
                    throw new \InvalidArgumentException(sprintf(
                        'Transformer [%s] configured in data-mapper group [%s] must implement %s.',
                        is_object($transformer) ? $transformer::class : (string) $transformer,
                        $group,
                        TransformerInterface::class,
                    ));
                }

                $valueTransformer->registerTransformer($resolved, (string) $group);
            }
        }
    }
}
