<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Handler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Telephantast\MessageBus\Handler\CallableHandler;
use Telephantast\MessageBus\Handler\Mapping\HandlerDescriptor;

/**
 * @internal
 * @psalm-internal Telephantast\TelephantastBundle
 */
final class ServiceHandlerProvider implements HandlerProvider
{
    public function getHandlers(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            if ($definition->isSynthetic() || $definition->isAbstract()) {
                continue;
            }

            $reflectionClass = $container->getReflectionClass($definition->getClass(), throw: false);

            if ($reflectionClass === null) {
                continue;
            }

            foreach (HandlerDescriptor::fromClass($reflectionClass) as $handlerDescriptor) {
                $id = $handlerDescriptor->id ?? \sprintf('%s.%s', $serviceId, $handlerDescriptor->function->name);

                yield new HandlerBuilder(
                    id: $id,
                    descriptor: $handlerDescriptor,
                    handler: new Definition(CallableHandler::class, [
                        '$id' => $id,
                        '$handler' => [
                            $handlerDescriptor->function->isStatic() ? $handlerDescriptor->function->class : new Reference($serviceId),
                            $handlerDescriptor->function->name,
                        ],
                    ]),
                );
            }
        }
    }
}
