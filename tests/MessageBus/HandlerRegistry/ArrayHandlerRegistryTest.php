<?php

declare(strict_types=1);

namespace Telephantast\MessageBus\HandlerRegistry;

use PHPUnit\Framework\Attributes\CoversClass;
use Telephantast\MessageBus\HandlerRegistry;

/**
 * @internal
 */
#[CoversClass(className: ArrayHandlerRegistry::class)]
final class ArrayHandlerRegistryTest extends HandlerRegistryTestCase
{
    protected function createHandlerRegistry(array $messageClassToHandler): HandlerRegistry
    {
        return new ArrayHandlerRegistry($messageClassToHandler);
    }
}
