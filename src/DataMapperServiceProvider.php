<?php

declare(strict_types=1);

namespace Medox\DataMapper;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Medox\DataMapper\ColumnMatchers\RelaxedColumnMatcher;
use Medox\DataMapper\Contracts\ColumnMatcherInterface;
use Medox\DataMapper\Contracts\DataMapperInterface;
use Medox\DataMapper\Contracts\NormalizerInterface;
use Medox\DataMapper\Contracts\TransformerInterface;
use Medox\DataMapper\Contracts\ValueMatcherInterface;
use Medox\DataMapper\Normalizers\FormattingNormalizer;
use Medox\DataMapper\ValueMatchers\RelaxedValueMatcher;

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

        // Bound first: the matchers below resolve through the container and take it.
        $this->app->singleton(
            NormalizerInterface::class,
            fn (Application $app): NormalizerInterface => $this->configured(
                $app, 'normalizer', NormalizerInterface::class, FormattingNormalizer::class,
            ),
        );

        $this->app->singleton(
            ColumnMatcherInterface::class,
            fn (Application $app): ColumnMatcherInterface => $this->configured(
                $app, 'column_matcher', ColumnMatcherInterface::class, RelaxedColumnMatcher::class,
            ),
        );

        $this->app->singleton(
            ValueMatcherInterface::class,
            fn (Application $app): ValueMatcherInterface => $this->configured(
                $app, 'value_matcher', ValueMatcherInterface::class, RelaxedValueMatcher::class,
            ),
        );

        $this->app->singleton(ValueTransformer::class, function (Application $app): ValueTransformer {
            $valueTransformer = new ValueTransformer($app->make(ValueMatcherInterface::class));

            $this->registerConfiguredGroups($app, $valueTransformer);

            return $valueTransformer;
        });

        $this->app->singleton(FieldExtractor::class, static fn (Application $app): FieldExtractor => new FieldExtractor(
            $app->make(ColumnMatcherInterface::class),
        ));
    }

    /**
     * Resolve one configured strategy, refusing anything that is not the right shape.
     *
     * A class that does not implement the contract is a configuration mistake worth
     * failing on at boot, rather than a mapping that silently does nothing halfway
     * through a source.
     *
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @param  class-string<T>  $default
     * @return T
     */
    private function configured(Application $app, string $key, string $contract, string $default): object
    {
        $configured = $app['config']->get("data-mapper.{$key}", $default);

        $resolved = is_string($configured) ? $app->make($configured) : $configured;

        if (! $resolved instanceof $contract) {
            throw new \InvalidArgumentException(sprintf(
                '[%s] configured in data-mapper.%s must implement %s.',
                is_object($configured) ? $configured::class : (string) $configured,
                $key,
                $contract,
            ));
        }

        return $resolved;
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
