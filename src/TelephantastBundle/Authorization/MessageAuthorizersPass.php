<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Authorization;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Telephantast\MessageBus\Authorization\Mapping\AuthorizerDescriptor;

/**
 * @internal
 * @psalm-internal Telephantast\TelephantastBundle
 */
final class MessageAuthorizersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('telephantast.authorizer_registry')) {
            return;
        }

        $authorizersByMessageClass = [];
        $passportClass = $container->getParameter('telephantast.passport_class');

        if (!\is_string($passportClass) || !class_exists($passportClass)) {
            throw new LogicException(\sprintf(
                'Invalid passport class "%s".',
                \is_string($passportClass) ? $passportClass : get_debug_type($passportClass),
            ));
        }

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            if ($definition->isSynthetic() || $definition->isAbstract()) {
                continue;
            }

            $reflectionClass = $container->getReflectionClass($definition->getClass(), throw: false);

            if ($reflectionClass === null) {
                continue;
            }

            foreach (AuthorizerDescriptor::fromClass($reflectionClass, $passportClass) as $authorizerDescriptor) {
                if ($authorizerDescriptor->function->isStatic()) {
                    $authorizer = [$reflectionClass->name, $authorizerDescriptor->function->name];
                } else {
                    $authorizer = [new Reference($serviceId), $authorizerDescriptor->function->name];
                }

                foreach ($authorizerDescriptor->messageClasses as $messageClass) {
                    if (isset($authorizersByMessageClass[$messageClass])) {
                        throw new LogicException(\sprintf('Authorizer for %s is already defined.', $messageClass));
                    }

                    $authorizersByMessageClass[$messageClass] = $authorizer;
                }
            }
        }

        $container
            ->findDefinition('telephantast.authorizer_registry')
            ->replaceArgument('$authorizersByMessageClass', new ServiceLocatorArgument($authorizersByMessageClass));
    }
}
