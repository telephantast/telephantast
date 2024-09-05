<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\EntityHandler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Telephantast\Message\Message;
use Telephantast\MessageBus\EntityHandler\EntityFactoryHandler;
use Telephantast\MessageBus\EntityHandler\EntityHandler;
use Telephantast\MessageBus\EntityHandler\FactoryMethod;
use Telephantast\MessageBus\EntityHandler\FindBy;
use Telephantast\MessageBus\Handler\Mapping\HandlerDescriptor;
use Telephantast\MessageBus\Reflection\AttributeReader;
use Telephantast\TelephantastBundle\Handler\HandlerBuilder;
use Telephantast\TelephantastBundle\Handler\HandlerProvider;
use Telephantast\TelephantastBundle\TelephantastBundle;

/**
 * @internal
 * @psalm-internal Telephantast\TelephantastBundle
 * @psalm-import-type Entities from TelephantastBundle
 */
final class EntityHandlerProvider implements HandlerProvider
{
    public function getHandlers(ContainerBuilder $container): iterable
    {
        /** @var Entities $entities */
        $entities = $container->getParameter('telephantast.entities_config');

        foreach ($entities as $entityClass => ['finder_id' => $finderId, 'saver_id' => $saverId]) {
            $entityClass = $container->getReflectionClass($entityClass);
            \assert($entityClass !== null);
            $defaultFindBy = AttributeReader::firstAttribute($entityClass, FindBy::class)?->newInstance();
            $defaultFactoryMethod = AttributeReader::firstAttribute($entityClass, FactoryMethod::class)?->newInstance();

            foreach (HandlerDescriptor::fromClass($entityClass) as $handlerDescriptor) {
                $handlerId = $handlerDescriptor->id ?? $handlerDescriptor->functionName();

                $findBy = AttributeReader::firstAttribute($handlerDescriptor->function, FindBy::class)
                    ?->newInstance()
                    ?? $defaultFindBy
                    ?? throw new \LogicException(\sprintf('No FindBy for %s', $handlerId));

                $factoryMethod = null;

                if (!$handlerDescriptor->function->isStatic()) {
                    $factoryMethod = AttributeReader::firstAttribute($handlerDescriptor->function, FactoryMethod::class)
                        ?->newInstance()
                        ?? $defaultFactoryMethod;
                }

                foreach ($handlerDescriptor->messageClasses as $messageClass) {
                    /** @var \ReflectionClass<Message> */
                    $messageClass = $container->getReflectionClass($messageClass);
                    $findBy->checkValidFor($entityClass, $messageClass);

                    if (!$handlerDescriptor->function->isStatic()) {
                        $factoryMethod?->checkValidFor($entityClass, $messageClass);
                    }
                }

                if ($handlerDescriptor->function->isStatic()) {
                    yield new HandlerBuilder($handlerId, $handlerDescriptor, new Definition(EntityFactoryHandler::class, [
                        '$id' => $handlerId,
                        '$class' => $entityClass->name,
                        '$finder' => new Reference($finderId),
                        '$findBy' => (new Definition(FindBy::class))->setFactory('unserialize')->addArgument(serialize($findBy)),
                        '$factoryMethod' => $handlerDescriptor->function->name,
                        '$saver' => new Reference($saverId),
                    ]));

                    continue;
                }

                yield new HandlerBuilder($handlerId, $handlerDescriptor, new Definition(EntityHandler::class, [
                    '$id' => $handlerId,
                    '$class' => $entityClass->name,
                    '$finder' => new Reference($finderId),
                    '$findBy' => (new Definition(FindBy::class))->setFactory('unserialize')->addArgument(serialize($findBy)),
                    '$factoryMethod' => $factoryMethod?->name,
                    '$handlerMethod' => $handlerDescriptor->function->name,
                    '$saver' => new Reference($saverId),
                ]));
            }
        }
    }
}
