<?php

declare(strict_types=1);

namespace Telephantast\Demo;

use Telephantast\BunnyTransport\BunnyConnectionPool;
use Telephantast\BunnyTransport\BunnyConsume;
use Telephantast\BunnyTransport\BunnyPublish;
use Telephantast\BunnyTransport\BunnySetup;
use Telephantast\MessageBus\Async\ClassBasedExchangeResolver;
use Telephantast\MessageBus\Async\Consumer;
use Telephantast\MessageBus\Async\ObjectSerializer;
use Telephantast\MessageBus\Async\PublishHandler;
use Telephantast\MessageBus\Async\SetupAndPublishOutboxMiddleware;
use Telephantast\MessageBus\Deduplication\DeduplicateMiddleware;
use Telephantast\MessageBus\Handler\CallableHandler;
use Telephantast\MessageBus\Handler\EventHandlers;
use Telephantast\MessageBus\HandlerRegistry\ArrayHandlerRegistry;
use Telephantast\MessageBus\MessageBus;
use Telephantast\MessageBus\MessageContext;
use Telephantast\MessageBus\MessageId\AddMessageIdMiddleware;
use Telephantast\MessageBus\Transaction\WrapInTransactionMiddleware;
use Telephantast\PdoPersistence\PdoTransactionProvider;
use Telephantast\PdoPersistence\PostgresDeduplicator;
use Telephantast\PdoPersistence\PostgresOutboxRepository;
use function Amp\trapSignal;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/messages.php';

const PING_RECEIVER_QUEUE = 'ping_receiver';

// Setup PostgreSQL database
$postgresConnection = new \PDO('pgsql:host=postgres;port=5432;dbname=app;user=app;password=!ChangeMe!');
$deduplicator = new PostgresDeduplicator($postgresConnection, table: 'deduplication');
$deduplicator->createTable();
$outboxRepository = new PostgresOutboxRepository($postgresConnection, table: 'outbox');
$outboxRepository->createTable();
$transactionProvider = new PdoTransactionProvider($postgresConnection);

// Setup RabbitMQ
$exchangeResolver = new ClassBasedExchangeResolver();
$objectNormalizer = new ObjectSerializer();
$rabbitPublishPool = new BunnyConnectionPool(host: 'rabbitmq');
$rabbitConsumePool = new BunnyConnectionPool(host: 'rabbitmq');
$transportSetup = new BunnySetup($rabbitPublishPool);
$transportPublish = new BunnyPublish($rabbitPublishPool, $objectNormalizer);
$transportConsume = new BunnyConsume($rabbitConsumePool, $objectNormalizer);
$transportSetup->setup([
    $exchangeResolver->resolve(Ping::class) => [PING_RECEIVER_QUEUE],
    $exchangeResolver->resolve(Pong::class) => [],
]);

// Handle pong locally + publish to RabbitMQ
$messageBus = new MessageBus(
    handlerRegistry: new ArrayHandlerRegistry([
        Pong::class => new EventHandlers([
            new PublishHandler(),
            new CallableHandler('local pong subscriber', static function (Pong $pong): void {
                var_dump($pong);
            }),
        ]),
    ]),
    middlewares: [
        new AddMessageIdMiddleware(),
        new SetupAndPublishOutboxMiddleware($outboxRepository, $transportPublish, $exchangeResolver),
        new WrapInTransactionMiddleware($transactionProvider),
    ],
);

// Consume Ping and respond with Pong
$consumer = new Consumer(
    queue: PING_RECEIVER_QUEUE,
    handlerRegistry: new ArrayHandlerRegistry([
        Ping::class => new CallableHandler('ping handler', static function (Ping $ping, MessageContext $context): void {
            var_dump($ping);
            $context->dispatch(new Pong());
        }),
    ]),
    middlewares: [
        new SetupAndPublishOutboxMiddleware($outboxRepository, $transportPublish, $exchangeResolver),
        new WrapInTransactionMiddleware($transactionProvider),
        new DeduplicateMiddleware($deduplicator),
    ],
    messageBus: $messageBus,
);
$transportConsume->runConsumer($consumer);

trapSignal([SIGINT, SIGTERM]);

$transportConsume->stopConsumer($consumer);
$rabbitPublishPool->disconnect();
$rabbitConsumePool->disconnect();
