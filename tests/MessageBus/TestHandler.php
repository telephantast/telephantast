<?php

declare(strict_types=1);

namespace Telephantast\MessageBus;

/**
 * @internal
 * @implements Handler<void, TestMessage>
 */
final class TestHandler implements Handler
{
    public function id(): string
    {
        return 'test';
    }

    public function handle(MessageContext $messageContext): mixed
    {
        return null;
    }
}
