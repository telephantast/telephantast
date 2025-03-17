<?php

declare(strict_types=1);

namespace Telephantast\Psr\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * @internal
 * @template-covariant TItem
 * @implements ContainerInterface<TItem>
 */
final class ArrayPsrContainer implements ContainerInterface
{
    /**
     * @param array<string, TItem> $items
     */
    public function __construct(
        private readonly array $items,
    ) {}

    public function get(string $id): mixed
    {
        return $this->items[$id]
            ?? throw new class (\sprintf('Item with key "%s" not found.', $id)) extends \RuntimeException implements NotFoundExceptionInterface {};
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->items);
    }
}
