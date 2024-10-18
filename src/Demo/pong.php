<?php

declare(strict_types=1);

namespace Telephantast\Demo;

use Telephantast\BunnyTransport\BunnyConnectionPool;
use Telephantast\BunnyTransport\BunnyConsume;
use Telephantast\BunnyTransport\BunnyPublish;
use Telephantast\BunnyTransport\BunnySetup;
use Telephantast\MessageBus\Async\AddExchangeMiddleware;
use Telephantast\MessageBus\Async\Consumer;
use Telephantast\MessageBus\Async\MessageClassBasedExchangeResolver;
use Telephantast\MessageBus\Async\ObjectSerializer;
use Telephantast\MessageBus\Async\Publisher;
use Telephantast\MessageBus\CreatedAt\AddCreatedAtMiddleware;
use Telephantast\MessageBus\Handler\CallableHandler;
use Telephantast\MessageBus\Handler\HandlerWithMiddlewares;
use Telephantast\MessageBus\HandlerRegistry\ArrayHandlerRegistry;
use Telephantast\MessageBus\MessageBus;
use Telephantast\MessageBus\MessageContext;
use Telephantast\MessageBus\MessageId\AddCausationIdMiddleware;
use Telephantast\MessageBus\MessageId\AddCorrelationIdMiddleware;
use Telephantast\MessageBus\MessageId\AddMessageIdMiddleware;
use Telephantast\MessageBus\Outbox\OutboxConsumerMiddleware;
use Telephantast\MessageBus\Outbox\TryPublishViaOutboxMiddleware;
use Telephantast\PdoPersistence\PdoTransactionProvider;
use Telephantast\PdoPersistence\PostgresOutboxPdoStorage;
use function Amp\trapSignal;

/** @psalm-suppress MissingFile */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/messages.php';

const QUEUE = 'pong';

// Setup Queue
$exchangeResolver = new MessageClassBasedExchangeResolver();
$objectNormalizer = new ObjectSerializer();
$publishPool = new BunnyConnectionPool(host: 'rabbitmq');
$consumePool = new BunnyConnectionPool(host: 'rabbitmq');
$transportSetup = new BunnySetup($publishPool);
$transportPublish = new BunnyPublish($publishPool, $objectNormalizer);
$transportConsume = new BunnyConsume($consumePool, $objectNormalizer);
$transportSetup->setup([
    $exchangeResolver->resolve(Ping::class) => [QUEUE],
    $exchangeResolver->resolve(Pong::class) => [],
]);

// Setup Outbox
$postgres = new \PDO('pgsql:host=postgres;port=5432;dbname=app;user=app;password=!ChangeMe!');
$transactionProvider = new PdoTransactionProvider($postgres);
$outboxStorage = new PostgresOutboxPdoStorage($postgres, table: 'outbox');
$outboxStorage->setup();

// Publish Pong
$messageBus = new MessageBus(
    handlerRegistry: new ArrayHandlerRegistry([
        Pong::class => new HandlerWithMiddlewares(new Publisher($transportPublish), [
            new TryPublishViaOutboxMiddleware(),
        ]),
    ]),
    middlewares: [
        new AddMessageIdMiddleware(),
        new AddCausationIdMiddleware(),
        new AddCorrelationIdMiddleware(),
        new AddCreatedAtMiddleware(),
        new AddExchangeMiddleware(),
    ],
);

// Consume Ping and dispatch Pong
/** @psalm-suppress InvalidArgument */
$consumer = new Consumer(
    queue: QUEUE,
    handlerRegistry: new ArrayHandlerRegistry([
        Ping::class => new CallableHandler('ping handler', static function (Ping $ping, MessageContext $context): void {
            var_dump($ping);
            $context->dispatch(new Pong());
        }),
    ]),
    middlewares: [
        new OutboxConsumerMiddleware($outboxStorage, $transactionProvider, $transportPublish),
    ],
    messageBus: $messageBus,
);
$transportConsume->runConsumer($consumer);

trapSignal([SIGINT, SIGTERM]);

$publishPool->disconnect();
$consumePool->disconnect();
