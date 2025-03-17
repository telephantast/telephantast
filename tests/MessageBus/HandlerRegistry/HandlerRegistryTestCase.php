<?php

declare(strict_types=1);

namespace Telephantast\MessageBus\HandlerRegistry;

use PHPUnit\Framework\TestCase;
use Telephantast\Message\Message;
use Telephantast\MessageBus\Handler;
use Telephantast\MessageBus\HandlerRegistry;
use Telephantast\MessageBus\TestEvent;
use Telephantast\MessageBus\TestHandler;
use Telephantast\MessageBus\TestMessage;

/**
 * @internal
 */
abstract class HandlerRegistryTestCase extends TestCase
{
    /**
     * @template TResult
     * @template TMessage of Message<TResult>
     * @param array<class-string<TMessage>, Handler<TResult, TMessage>> $messageClassToHandler
     */
    abstract protected function createHandlerRegistry(array $messageClassToHandler): HandlerRegistry;

    final public function testGet(): void
    {
        $handler = new TestHandler();

        $handlerRegistry = $this->createHandlerRegistry([
            TestMessage::class => $handler,
        ]);

        self::assertSame($handler, $handlerRegistry->get(TestMessage::class));
    }

    final public function testGetHandlerNotFound(): void
    {
        self::expectException(HandlerNotFound::class);

        $handlerRegistry = $this->createHandlerRegistry([]);

        $handlerRegistry->get(TestMessage::class);
    }

    final public function testGetHandlerForEventNotFound(): void
    {
        $handlerRegistry = $this->createHandlerRegistry([]);

        $handler = $handlerRegistry->get(TestEvent::class);

        self::assertInstanceOf(Handler\CallableHandler::class, $handler);
    }

    final public function testFind(): void
    {
        $handler = new TestHandler();

        $handlerRegistry = $this->createHandlerRegistry([
            TestMessage::class => $handler,
        ]);

        self::assertSame($handler, $handlerRegistry->find(TestMessage::class));
    }

    final public function testFindHandlerNotFound(): void
    {
        $handlerRegistry = $this->createHandlerRegistry([]);

        self::assertNull($handlerRegistry->find(TestMessage::class));
    }
}
