<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Handler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @api
 * @psalm-type CallableConfigurator = callable(HandlerBuilder, ContainerBuilder): void
 */
final class HandlerMiddlewareConfigurators
{
    /**
     * @var ?\WeakMap<ContainerBuilder, non-empty-list<CallableConfigurator>>
     */
    private static ?\WeakMap $configuratorsByContainer = null;

    /**
     * @param list<CallableConfigurator> $configurators
     */
    private function __construct(
        private readonly ContainerBuilder $container,
        private readonly array $configurators,
    ) {}

    /**
     * @param CallableConfigurator $configurator
     */
    public static function register(ContainerBuilder $container, callable $configurator): void
    {
        self::configuratorsByContainer()[$container] = [...(self::configuratorsByContainer()[$container] ?? []), $configurator];

        if (\is_object($configurator)) {
            $container->addObjectResource($configurator);
        } elseif (\is_array($configurator)) {
            /** @var array{object|class-string, non-empty-string} $configurator */
            $container->addObjectResource($configurator[0]);
        }
    }

    public static function forContainer(ContainerBuilder $container): self
    {
        $configurators = self::configuratorsByContainer()[$container] ?? [];

        foreach ($container->findTaggedServiceIds('telephantast.handler_middleware') as $serviceId => $attributesList) {
            /** @var list<array> $attributesList */
            $priority = array_merge(...$attributesList)['priority'] ?? 0;
            \assert(\is_int($priority));
            $configurators[] = static function (HandlerBuilder $handlerBuilder) use ($priority, $serviceId): void {
                $handlerBuilder->addMiddleware($priority, new Reference($serviceId));
            };
        }

        return new self($container, $configurators);
    }

    /**
     * @return \WeakMap<ContainerBuilder, non-empty-list<CallableConfigurator>>
     */
    private static function configuratorsByContainer(): \WeakMap
    {
        if (self::$configuratorsByContainer === null) {
            /** @var \WeakMap<ContainerBuilder, non-empty-list<CallableConfigurator>> */
            self::$configuratorsByContainer = new \WeakMap();
        }

        return self::$configuratorsByContainer;
    }

    public function configure(HandlerBuilder $handlerBuilder): void
    {
        foreach ($this->configurators as $configurator) {
            ($configurator)($handlerBuilder, $this->container);
        }
    }
}
