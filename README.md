# Telephantast

Flexible PHP Message Bus with middleware, handler result support and transactional outbox for 100% reliable messaging. 

## Quick start

```php
use Telephantast\Message\Event;
use Telephantast\Message\Message;
use Telephantast\MessageBus\Handler\CallableHandler;
use Telephantast\MessageBus\HandlerRegistry\ArrayHandlerRegistry;
use Telephantast\MessageBus\MessageBus;
use Telephantast\MessageBus\MessageContext;

/**
 * @psalm-immutable
 * @implements Message<string>
 */
final readonly class Ping implements Message {}

/**
 * @psalm-immutable
 */
final readonly class Pong implements Event {}

$messageBus = new MessageBus(new ArrayHandlerRegistry([
    Ping::class => new CallableHandler('handle ping', function (Ping $ping, MessageContext $context): string {
        var_dump($ping);
        $context->dispatch(new Pong());
        
        return 'Hello, World!';
    }),
    Pong::class => new CallableHandler('on pong', function (Pong $pong): void {
        var_dump($pong);
    }),
]));

var_dump($messageBus->dispatch(new Ping()));

// class Ping#42 (0) {}
// class Pong#45 (0) {}
// string(13) "Hello, World!"
```

## Full demo with async RabbitMQ transport and transactional outbox

```shell
# Setup
composer create-project --stability=dev telephantast/demo telephantast
cd telephantast
docker-compose up --remove-orphans --detach --build

# Send ping and start wait for pong
docker-compose run -it php php ping_sender.php

# In separate process handle ping and reply with pong
docker-compose run -it php php ping_receiver.php
```
