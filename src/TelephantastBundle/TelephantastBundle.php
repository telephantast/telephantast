<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ParametersConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Telephantast\BunnyTransport\BunnyConnectionPool;
use Telephantast\BunnyTransport\BunnyConsume;
use Telephantast\BunnyTransport\BunnyPublish;
use Telephantast\BunnyTransport\BunnySetup;
use Telephantast\MessageBus\Async\AddExchangeMiddleware;
use Telephantast\MessageBus\Async\Consumer;
use Telephantast\MessageBus\Async\MessageClassBasedExchangeResolver;
use Telephantast\MessageBus\Async\ObjectSerializer;
use Telephantast\MessageBus\Async\Publisher;
use Telephantast\MessageBus\Authorization\AuthenticateMiddleware;
use Telephantast\MessageBus\Authorization\AuthorizeMiddleware;
use Telephantast\MessageBus\Authorization\AuthorizerRegistry;
use Telephantast\MessageBus\CreatedAt\AddCreatedAtMiddleware;
use Telephantast\MessageBus\Handler\HandlerWithMiddlewares;
use Telephantast\MessageBus\Logging\LogHandlerMiddleware;
use Telephantast\MessageBus\Logging\LogPublisherMiddleware;
use Telephantast\MessageBus\MessageBus;
use Telephantast\MessageBus\MessageId\AddCausationIdMiddleware;
use Telephantast\MessageBus\MessageId\AddCorrelationIdMiddleware;
use Telephantast\MessageBus\MessageId\AddMessageIdMiddleware;
use Telephantast\MessageBus\Outbox\OutboxConsumerMiddleware;
use Telephantast\MessageBus\Outbox\OutboxHandlerMiddleware;
use Telephantast\MessageBus\Outbox\TryPublishViaOutboxMiddleware;
use Telephantast\TelephantastBundle\Async\ConsumeConsoleCommand;
use Telephantast\TelephantastBundle\Async\SetupConsoleCommand;
use Telephantast\TelephantastBundle\Authorization\MessageAuthorizersPass;
use Telephantast\TelephantastBundle\EntityHandler\EntityHandlerProvider;
use Telephantast\TelephantastBundle\Handler\HandlerMiddlewareConfigurators;
use Telephantast\TelephantastBundle\Handler\ServiceHandlerProvider;
use Telephantast\TelephantastBundle\Mapping\ConsumerMiddleware;
use Telephantast\TelephantastBundle\Mapping\HandlerMiddleware;
use Telephantast\TelephantastBundle\Mapping\HandlerMiddlewareWithConfigurator;
use Telephantast\TelephantastBundle\Mapping\MessageBusMiddleware;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * @api
 * @psalm-type Entities = array<class-string, array{finder_id: non-empty-string, saver_id: non-empty-string}>
 * @psalm-type Config = array{
 *     logger_id: non-empty-string,
 *     created_at: array{enabled: bool, clock_id?: non-empty-string, priority: int},
 *     message_id: array{enabled: bool, generator_id?: non-empty-string, priority: int},
 *     causation_id: array{enabled: bool, priority: int},
 *     correlation_id: array{enabled: bool, priority: int},
 *     authorization: array{
 *         enabled: bool,
 *         context_id: non-empty-string,
 *         passport_validator_id: non-empty-string,
 *         passport_class: class-string,
 *         authenticate_priority: int,
 *         authorize_priority: int,
 *     },
 *     async: array{
 *         enabled: bool,
 *         bunny: array{
 *             host: non-empty-string,
 *             port: int,
 *             user: non-empty-string,
 *             password: non-empty-string,
 *             vhost: non-empty-string,
 *             heartbeat: int,
 *         },
 *         prefetch_count: int,
 *         object_normalizer_id: non-empty-string,
 *         object_denormalizer_id: non-empty-string,
 *         exchange_resolver_id: non-empty-string,
 *         outbox: array{
 *             enabled: bool,
 *             transaction_provider_id: non-empty-string,
 *             storage_id: non-empty-string,
 *         },
 *     },
 *     entity_finder_id: ?non-empty-string,
 *     entity_saver_id: ?non-empty-string,
 *     entities: Entities,
 * }
 */
final class TelephantastBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new MessageBusPass([new ServiceHandlerProvider(), new EntityHandlerProvider()]));
        $container->addCompilerPass(new MessageAuthorizersPass());

        $container->registerAttributeForAutoconfiguration(
            MessageBusMiddleware::class,
            static function (ChildDefinition $definition, MessageBusMiddleware $attribute): void {
                $definition->addTag('telephantast.message_bus_middleware', ['priority' => $attribute->priority]);
            },
        );
        $container->registerAttributeForAutoconfiguration(
            ConsumerMiddleware::class,
            static function (ChildDefinition $definition, ConsumerMiddleware $attribute): void {
                $definition->addTag('telephantast.consumer_middleware', ['priority' => $attribute->priority]);
            },
        );
        $container->registerAttributeForAutoconfiguration(
            HandlerMiddleware::class,
            static function (ChildDefinition $definition, HandlerMiddleware $attribute): void {
                $definition->addTag('telephantast.handler_middleware', ['priority' => $attribute->priority]);
            },
        );
        $container->registerAttributeForAutoconfiguration(
            HandlerMiddlewareWithConfigurator::class,
            static function (ChildDefinition $_definition, HandlerMiddlewareWithConfigurator $attribute) use ($container): void {
                HandlerMiddlewareConfigurators::register($container, $attribute->configurator);
            },
        );
    }

    /**
     * @psalm-suppress UndefinedMethod
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var NodeBuilder */
        $children = $definition->rootNode()->children();

        $children->scalarNode('logger_id')->cannotBeEmpty()->defaultValue('logger');

        $this->configureCreatedAt($children);
        $this->configureMessageId($children);
        $this->configureCausationId($children);
        $this->configureCorrelationId($children);
        $this->configureAuthorization($children);
        $this->configureAsync($children);
        $this->configureEntityHandler($definition->rootNode());
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        $parameters = $container->parameters();
        $parameters->set('telephantast.entities_config', $config['entities']);

        $services = $container->services();
        $services
            ->set(MessageBus::class)
                ->args([
                    '$handlerRegistry' => abstract_arg('Message bus handler registry.'),
                    '$middlewares' => tagged_iterator('telephantast.message_bus_middleware'),
                ])
            ->set('telephantast.log_handler_middleware', LogHandlerMiddleware::class)
                ->args([
                    '$logger' => service($config['logger_id']),
                ])
                ->tag('telephantast.handler_middleware', ['priority' => -1000]);

        $this->loadCreatedAt($config, $services);
        $this->loadMessageId($config, $services);
        $this->loadCausationId($config, $services);
        $this->loadCorrelationId($config, $services);
        $this->loadAuthorization($config, $parameters, $services);
        $this->loadAsync($config, $services);
    }

    /**
     * @psalm-suppress UndefinedInterfaceMethod, MixedMethodCall
     */
    private function configureCreatedAt(NodeBuilder $config): void
    {
        $config
            ->arrayNode('created_at')
                ->canBeDisabled()
                ->children()
                    ->integerNode('priority')->defaultValue(1000)->end()
                    ->scalarNode('clock_id')->cannotBeEmpty()->defaultNull()->end();
    }

    /**
     * @param Config $config
     */
    private function loadCreatedAt(array $config, ServicesConfigurator $services): void
    {
        if ($config['created_at']['enabled']) {
            $services
                ->set('telephantast.add_created_at_middleware', AddCreatedAtMiddleware::class)
                    ->args(isset($config['created_at']['clock_id']) ? [service($config['created_at']['clock_id'])] : [])
                    ->tag('telephantast.message_bus_middleware', ['priority' => $config['created_at']['priority']]);
        }
    }

    /**
     * @psalm-suppress UndefinedInterfaceMethod, MixedMethodCall
     */
    private function configureMessageId(NodeBuilder $config): void
    {
        $config
            ->arrayNode('message_id')
                ->canBeDisabled()
                ->children()
                    ->scalarNode('generator_id')->cannotBeEmpty()->defaultNull()->end()
                    ->integerNode('priority')->defaultValue(950)->end();
    }

    /**
     * @param Config $config
     */
    private function loadMessageId(array $config, ServicesConfigurator $services): void
    {
        if ($config['message_id']['enabled']) {
            $services
                ->set('telephantast.add_message_id_middleware', AddMessageIdMiddleware::class)
                    ->args(isset($config['message_id']['generator_id']) ? [service($config['message_id']['generator_id'])] : [])
                    ->tag('telephantast.message_bus_middleware', ['priority' => $config['message_id']['priority']]);
        }
    }

    /**
     * @psalm-suppress UnusedMethodCall
     */
    private function configureCausationId(NodeBuilder $config): void
    {
        $config
            ->arrayNode('causation_id')
                ->canBeDisabled()
                ->children()
                    ->integerNode('priority')->defaultValue(900)->end();
    }

    /**
     * @param Config $config
     */
    private function loadCausationId(array $config, ServicesConfigurator $services): void
    {
        if ($config['causation_id']['enabled']) {
            $services
                ->set('telephantast.add_causation_id_middleware', AddCausationIdMiddleware::class)
                    ->tag('telephantast.message_bus_middleware', ['priority' => $config['causation_id']['priority']]);
        }
    }

    /**
     * @psalm-suppress UnusedMethodCall
     */
    private function configureCorrelationId(NodeBuilder $config): void
    {
        $config
            ->arrayNode('correlation_id')
                ->canBeDisabled()
                ->children()
                    ->integerNode('priority')->defaultValue(850)->end();
    }

    /**
     * @param Config $config
     */
    private function loadCorrelationId(array $config, ServicesConfigurator $services): void
    {
        if ($config['correlation_id']['enabled']) {
            $services
                ->set('telephantast.add_correlation_id_middleware', AddCorrelationIdMiddleware::class)
                    ->tag('telephantast.message_bus_middleware', ['priority' => $config['correlation_id']['priority']]);
        }
    }

    /**
     * @psalm-suppress UndefinedInterfaceMethod, MixedMethodCall
     */
    private function configureAuthorization(NodeBuilder $config): void
    {
        $config
            ->arrayNode('authorization')
                ->canBeEnabled()
                ->children()
                    ->scalarNode('context_id')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('passport_validator_id')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('passport_class')
                        ->isRequired()
                        ->validate()
                            ->ifTrue(static fn(string $value): bool => !class_exists($value))
                            ->thenInvalid('Invalid passport class %s')
                        ->end()
                    ->end()
                    ->integerNode('authenticate_priority')->defaultValue(100)->end()
                    ->integerNode('authorize_priority')->defaultValue(1000)->end()
                ->end()
            ->end();
    }

    /**
     * @param Config $config
     */
    private function loadAuthorization(array $config, ParametersConfigurator $parameters, ServicesConfigurator $services): void
    {
        if (!$config['authorization']['enabled']) {
            return;
        }

        $authorization = $config['authorization'];

        $parameters->set('telephantast.passport_class', $authorization['passport_class']);
        $services
            ->set('telephantast.authorizer_registry', AuthorizerRegistry::class)
                ->args([
                    '$authorizersByMessageClass' => abstract_arg('Message authorizers indexed by message class.'),
                ]);
        $services
            ->set('telephantast.authenticate_middleware', AuthenticateMiddleware::class)
                ->args([
                    '$authenticationContext' => service($authorization['context_id']),
                ])
                ->tag('telephantast.message_bus_middleware', ['priority' => $authorization['authenticate_priority']]);
        $services
            ->set('telephantast.authorize_middleware', AuthorizeMiddleware::class)
                ->args([
                    '$passportValidator' => service($authorization['passport_validator_id']),
                    '$authorizerRegistry' => service('telephantast.authorizer_registry'),
                ])
                ->tag('telephantast.handler_middleware', ['priority' => $authorization['authorize_priority']]);
    }

    /**
     * @psalm-suppress UndefinedInterfaceMethod, MixedMethodCall
     */
    private function configureAsync(NodeBuilder $config): void
    {
        $config
            ->arrayNode('async')
                ->canBeEnabled()
                ->children()
                    ->arrayNode('bunny')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('host')->cannotBeEmpty()->defaultValue('localhost')->end()
                            ->scalarNode('port')->cannotBeEmpty()->defaultValue(5672)->end()
                            ->scalarNode('user')->cannotBeEmpty()->defaultValue('guest')->end()
                            ->scalarNode('password')->cannotBeEmpty()->defaultValue('guest')->end()
                            ->scalarNode('vhost')->cannotBeEmpty()->defaultValue('/')->end()
                            ->integerNode('heartbeat')->info('heartbeat in seconds')->defaultValue(60)->end()
                        ->end()
                    ->end()
                    ->integerNode('prefetch_count')->defaultValue(100)->end()
                    ->scalarNode('object_normalizer_id')
                        ->cannotBeEmpty()
                        ->defaultValue('telephantast.object_normalizer.serializer')
                    ->end()
                    ->scalarNode('object_denormalizer_id')
                        ->cannotBeEmpty()
                        ->defaultValue('telephantast.object_normalizer.serializer')
                    ->end()
                    ->scalarNode('exchange_resolver_id')
                        ->cannotBeEmpty()
                        ->defaultValue('telephantast.exchange_resolver.message_class_based')
                    ->end()
                    ->arrayNode('outbox')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('transaction_provider_id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('storage_id')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end();
    }

    /**
     * @param Config $config
     */
    private function loadAsync(array $config, ServicesConfigurator $services): void
    {
        if (!$config['async']['enabled']) {
            return;
        }

        $async = $config['async'];

        $services
            ->set('telephantast.object_normalizer.serializer', ObjectSerializer::class)
            // Bunny
            ->set('telephantast.bunny_connection_pool', BunnyConnectionPool::class)
                ->args([
                    '$host' => $async['bunny']['host'],
                    '$port' => $async['bunny']['port'],
                    '$user' => $async['bunny']['user'],
                    '$password' => $async['bunny']['password'],
                    '$vhost' => $async['bunny']['vhost'],
                    '$heartbeatSeconds' => $async['bunny']['heartbeat'],
                ])
            ->set('telephantast.transport_setup', BunnySetup::class)
                ->args([
                    '$connectionPool' => service('telephantast.bunny_connection_pool'),
                ])
            ->set('telephantast.transport_publish', BunnyPublish::class)
                ->args([
                    '$connectionPool' => service('telephantast.bunny_connection_pool'),
                    '$objectNormalizer' => service($async['object_normalizer_id']),
                ])
            ->set('telephantast.transport_consume', BunnyConsume::class)
                ->args([
                    '$connectionPool' => inline_service()->parent('telephantast.bunny_connection_pool'),
                    '$objectDenormalizer' => service($async['object_denormalizer_id']),
                    '$prefetchCount' => $async['prefetch_count'],
                ])
            // Setup
            ->set('telephantast.setup_console_command', SetupConsoleCommand::class)
                ->args([
                    '$transportSetup' => service('telephantast.transport_setup'),
                    '$exchangeResolver' => service($async['exchange_resolver_id']),
                    '$messageClassesToQueues' => abstract_arg('Message classes to queues'),
                ])
                ->tag('console.command', ['command' => 'telephantast:setup'])
            // Publish
            ->set('telephantast.exchange_resolver.message_class_based', MessageClassBasedExchangeResolver::class)
            ->set('telephantast.publisher', HandlerWithMiddlewares::class)
                ->args([
                    '$handler' => inline_service(Publisher::class)
                        ->args([
                            '$transportPublish' => service('telephantast.transport_publish'),
                        ]),
                    '$middlewares' => array_filter([
                        $async['outbox']['enabled'] ? inline_service(TryPublishViaOutboxMiddleware::class) : null,
                        inline_service(LogPublisherMiddleware::class)
                            ->args([
                                '$logger' => service($config['logger_id']),
                            ]),
                    ]),
                ])
            ->set('telephantast.add_exchange_middleware', AddExchangeMiddleware::class)
                ->args([
                    '$exchangeResolver' => service($async['exchange_resolver_id']),
                ])
                ->tag('telephantast.message_bus_middleware')
            // Consume
            ->set('telephantast.consumer', Consumer::class)
                ->abstract()
                ->args([
                    '$queue' => abstract_arg('Queue'),
                    '$handlerRegistry' => abstract_arg('Handler registry'),
                    '$middlewares' => tagged_iterator('telephantast.consumer_middleware'),
                    '$messageBus' => service(MessageBus::class),
                ])
            ->set('telephantast.consume_console_command', ConsumeConsoleCommand::class)
                ->args([
                    '$transportConsume' => service('telephantast.transport_consume'),
                    '$queueToConsumer' => abstract_arg('Queue to consumer'),
                ])
                ->tag('console.command', ['command' => 'telephantast:consume']);

        if ($async['outbox']['enabled']) {
            $services
                ->set('telephantast.outbox_handler_middleware', OutboxHandlerMiddleware::class)
                    ->args([
                        '$outboxStorage' => service($async['outbox']['storage_id']),
                        '$transactionProvider' => service($async['outbox']['transaction_provider_id']),
                        '$transportPublish' => service('telephantast.transport_publish'),
                    ])
                    ->tag('telephantast.handler_middleware', ['priority' => 500])
                ->set('telephantast.outbox_consumer_middleware', OutboxConsumerMiddleware::class)
                    ->args([
                        '$outboxStorage' => service($async['outbox']['storage_id']),
                        '$transactionProvider' => service($async['outbox']['transaction_provider_id']),
                        '$transportPublish' => service('telephantast.transport_publish'),
                    ])
                    ->tag('telephantast.consumer_middleware', ['priority' => 500]);
        }
    }

    /**
     * @psalm-suppress UndefinedMethod, MixedMethodCall
     */
    private function configureEntityHandler(NodeDefinition|ArrayNodeDefinition $config): void
    {
        $config
            ->children()
                ->scalarNode('entity_finder_id')->cannotBeEmpty()->defaultNull()->end()
                ->scalarNode('entity_saver_id')->cannotBeEmpty()->defaultNull()->end()
                ->arrayNode('entities')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('finder_id')->cannotBeEmpty()->defaultNull()->end()
                            ->scalarNode('saver_id')->cannotBeEmpty()->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->always(
                    /**
                     * @param array{
                     *     entity_finder_id: ?non-empty-string,
                     *     entity_saver_id: ?non-empty-string,
                     *     entities: array<array{finder_id: ?non-empty-string, saver_id: ?non-empty-string}>,
                     *     ...
                     * } $config
                     * @return array{entities: Entities, ...}
                     */
                    static function (array $config): array {
                        $entities = [];

                        foreach ($config['entities'] as $class => &$entity) {
                            if (!\is_string($class) || !class_exists($class)) {
                                throw new \InvalidArgumentException(\sprintf('Entity class %s does not exist', (string) $class));
                            }

                            $entities[$class] = [
                                'finder_id' => $entity['finder_id'] ?? $config['entity_finder_id'] ?? throw new \InvalidArgumentException(\sprintf(
                                    'Either set "entity_finder_id" or set "finder_id" for %s',
                                    $class,
                                )),
                                'saver_id' => $entity['saver_id'] ?? $config['entity_saver_id'] ?? throw new \InvalidArgumentException(\sprintf(
                                    'Either set "entity_saver_id" or set "saver_id" for %s',
                                    $class,
                                )),
                            ];
                        }

                        $config['entities'] = $entities;

                        return $config;
                    },
                );
    }
}
