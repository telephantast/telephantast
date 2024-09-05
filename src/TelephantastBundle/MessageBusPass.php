<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Telephantast\Message\Event;
use Telephantast\Message\Message;
use Telephantast\MessageBus\Async\Mapping\Async;
use Telephantast\MessageBus\Handler\EventHandlers;
use Telephantast\MessageBus\HandlerRegistry\ArrayHandlerRegistry;
use Telephantast\MessageBus\MessageBus;
use Telephantast\MessageBus\Reflection\AttributeReader;
use Telephantast\TelephantastBundle\Handler\HandlerMiddlewareConfigurators;
use Telephantast\TelephantastBundle\Handler\HandlerProvider;

/**
 * @internal
 * @psalm-internal Telephantast\TelephantastBundle
 */
final class MessageBusPass implements CompilerPassInterface
{
    /**
     * @param list<HandlerProvider> $handlerProviders
     */
    public function __construct(
        private readonly array $handlerProviders = [],
    ) {}

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(MessageBus::class)) {
            return;
        }

        $asyncEnabled = $container->hasDefinition('telephantast.publisher');
        $publisher = new Reference('telephantast.publisher');
        $handlerMiddlewareConfigurator = HandlerMiddlewareConfigurators::forContainer($container);

        /** @var array<class-string<Message>, non-empty-array<int|'publish', Definition|Reference>> */
        $messageBusHandlers = [];
        /** @var array<class-string<Message>, list<non-empty-string>> */
        $messageClassesToQueues = [];
        /** @var array<non-empty-string, non-empty-array<class-string<Message>, non-empty-list<Definition|Reference>>> */
        $queueToConsumerHandlers = [];

        foreach ($this->handlerProviders as $handlerProvider) {
            foreach ($handlerProvider->getHandlers($container) as $handlerBuilder) {
                $handlerMiddlewareConfigurator->configure($handlerBuilder);
                $handler = $handlerBuilder->build();
                $queue = AttributeReader::firstAttribute($handlerBuilder->descriptor->function, Async::class)?->newInstance()->queue;

                if ($queue !== null && !$asyncEnabled) {
                    throw new LogicException(\sprintf(
                        'Telephantast async functionality must be enabled to use #[Async] attribute in %s',
                        $handlerBuilder->descriptor->functionName(),
                    ));
                }

                foreach ($handlerBuilder->descriptor->messageClasses as $messageClass) {
                    if ($queue === null) {
                        $messageBusHandlers[$messageClass][] = $handler;
                    } else {
                        $messageBusHandlers[$messageClass]['publish'] ??= $publisher;
                        $messageClassesToQueues[$messageClass][] = $queue;
                        $queueToConsumerHandlers[$queue][$messageClass][] = $handler;
                    }
                }
            }
        }

        $container
            ->findDefinition(MessageBus::class)
            ->replaceArgument('$handlerRegistry', $this->buildHandlerRegistry($messageBusHandlers));

        if ($container->hasDefinition('telephantast.consume_console_command')) {
            $container
                ->findDefinition('telephantast.setup_console_command')
                ->replaceArgument('$messageClassesToQueues', array_map(array_unique(...), $messageClassesToQueues));

            $container
                ->findDefinition('telephantast.consume_console_command')
                ->replaceArgument('$queueToConsumer', $this->buildQueueToConsumerLocator($container, $queueToConsumerHandlers));
        }
    }

    /**
     * @param array<non-empty-string, non-empty-array<class-string<Message>, non-empty-list<Definition|Reference>>> $queueToConsumerHandlers
     */
    private function buildQueueToConsumerLocator(ContainerBuilder $container, array $queueToConsumerHandlers): ServiceLocatorArgument
    {
        $queueToConsumer = [];

        foreach ($queueToConsumerHandlers as $queue => $handlersByMessageClass) {
            $consumerId = 'telephantast.consumer.' . $queue;
            $container->setDefinition(
                $consumerId,
                (new ChildDefinition('telephantast.consumer'))
                ->replaceArgument('$queue', $queue)
                ->replaceArgument('$handlerRegistry', $this->buildHandlerRegistry($handlersByMessageClass)),
            );
            $queueToConsumer[$queue] = new Reference($consumerId);
        }

        return new ServiceLocatorArgument($queueToConsumer);
    }

    /**
     * @param array<class-string<Message>, non-empty-array<Definition|Reference>> $handlerArraysByMessageClass
     */
    private function buildHandlerRegistry(array $handlerArraysByMessageClass): Definition
    {
        /** @var array<class-string<Message>, Definition|Reference> */
        $handlersByMessageClass = [];

        foreach ($handlerArraysByMessageClass as $messageClass => $handlers) {
            if (\count($handlers) === 1) {
                $handlersByMessageClass[$messageClass] = $handlers[array_key_first($handlers)];

                continue;
            }

            if (!is_subclass_of($messageClass, Event::class)) {
                throw new LogicException(\sprintf(
                    'Non-event message %s must not have more than 1 handler, got %d.',
                    $messageClass,
                    \count($handlers),
                ));
            }

            $handlersByMessageClass[$messageClass] = new Definition(EventHandlers::class, [array_values($handlers)]);
        }

        return new Definition(ArrayHandlerRegistry::class, [$handlersByMessageClass]);
    }
}
