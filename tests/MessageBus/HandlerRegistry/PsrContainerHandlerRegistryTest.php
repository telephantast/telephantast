<?php

declare(strict_types=1);

namespace Telephantast\MessageBus\HandlerRegistry;

use PHPUnit\Framework\Attributes\CoversClass;
use Telephantast\MessageBus\HandlerRegistry;
use Telephantast\Psr\Container\ArrayPsrContainer;

/**
 * @internal
 */
#[CoversClass(className: PsrContainerHandlerRegistry::class)]
final class PsrContainerHandlerRegistryTest extends HandlerRegistryTestCase
{
    protected function createHandlerRegistry(array $messageClassToHandler): HandlerRegistry
    {
        return new PsrContainerHandlerRegistry(
            new ArrayPsrContainer($messageClassToHandler),
        );
    }
}
