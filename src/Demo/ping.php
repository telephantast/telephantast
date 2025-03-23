<?php

declare(strict_types=1);

namespace Telephantast\Demo;

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
use Telephantast\MessageBus\MessageId\AddCausationIdMiddleware;
use Telephantast\MessageBus\MessageId\AddCorrelationIdMiddleware;
use Telephantast\MessageBus\MessageId\AddMessageIdMiddleware;
use Telephantast\MessageBus\Outbox\OutboxConsumerMiddleware;
use Telephantast\MessageBus\Outbox\TryPublishViaOutboxMiddleware;
use Telephantast\PdoPersistence\PdoTransactionProvider;
use Telephantast\PdoPersistence\PostgresOutboxPdoStorage;
use Telephantast\ThesisAmqpTransport\ThesisConsume;
use Telephantast\ThesisAmqpTransport\ThesisPublish;
use Telephantast\ThesisAmqpTransport\ThesisSetup;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use function Amp\trapSignal;

/** @psalm-suppress MissingFile */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/messages.php';

const QUEUE_PONG = 'pong';
const QUEUE_PING = 'ping';

// Setup Queue
$exchangeResolver = new MessageClassBasedExchangeResolver();
$objectNormalizer = new ObjectSerializer();
$config = new Config(host: 'rabbitmq');
$publishClient = new Client($config);
$publishClient->connect();
$consumeClient = new Client($config);
$consumeClient->connect();
$transportSetup = new ThesisSetup($publishClient);
$transportPublish = new ThesisPublish($publishClient, $objectNormalizer);
$transportConsume = new ThesisConsume($consumeClient, $objectNormalizer);
$transportSetup->setup([
    $exchangeResolver->resolve(Ping::class) => [QUEUE_PONG],
    $exchangeResolver->resolve(Pong::class) => [QUEUE_PING],
]);

// Setup Outbox
$postgres = new \PDO('pgsql:host=postgres;port=5432;dbname=app;user=app;password=!ChangeMe!');
$transactionProvider = new PdoTransactionProvider($postgres);
$outboxStorage = new PostgresOutboxPdoStorage($postgres, table: 'outbox');
$outboxStorage->setup();

// Dispatch Ping
$messageBus = new MessageBus(
    handlerRegistry: new ArrayHandlerRegistry([
        Ping::class =>  new HandlerWithMiddlewares(new Publisher($transportPublish), [
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
$messageBus->dispatch(new Ping());

// Consume Pong
$consumer = new Consumer(
    queue: QUEUE_PING,
    handlerRegistry: new ArrayHandlerRegistry([
        Pong::class => new CallableHandler('pong subscriber', static function (Pong $pong): void {
            var_dump($pong);
        }),
    ]),
    middlewares: [
        new OutboxConsumerMiddleware($outboxStorage, $transactionProvider, $transportPublish),
    ],
    messageBus: $messageBus,
);
$transportConsume->runConsumer($consumer);

trapSignal([SIGINT, SIGTERM]);

$publishClient->disconnect();
$consumeClient->disconnect();
