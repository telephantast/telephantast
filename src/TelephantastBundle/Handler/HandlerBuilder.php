<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Handler;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Telephantast\MessageBus\Handler\HandlerWithMiddlewares;
use Telephantast\MessageBus\Handler\Mapping\HandlerDescriptor;

/**
 * @api
 * @psalm-internal Telephantast\TelephantastBundle
 */
final class HandlerBuilder
{
    /**
     * @var array<int, non-empty-list<Definition|Reference>>
     */
    private array $middlewaresByPriority = [];

    /**
     * @param non-empty-string $id
     */
    public function __construct(
        public readonly string $id,
        public readonly HandlerDescriptor $descriptor,
        private readonly Definition|Reference $handler,
    ) {}

    public function addMiddleware(int $priority, Definition|Reference $middleware): self
    {
        $this->middlewaresByPriority[$priority][] = $middleware;

        return $this;
    }

    public function build(): Definition|Reference
    {
        if ($this->middlewaresByPriority === []) {
            return $this->handler;
        }

        krsort($this->middlewaresByPriority);

        return new Definition(HandlerWithMiddlewares::class, [
            $this->handler,
            array_merge(...$this->middlewaresByPriority),
        ]);
    }
}
