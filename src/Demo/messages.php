<?php

declare(strict_types=1);

namespace Telephantast\Demo;

use Telephantast\Message\Event;
use Telephantast\Message\Message;

/**
 * @psalm-immutable
 * @implements Message<void>
 */
final readonly class Ping implements Message {}

/**
 * @psalm-immutable
 */
final readonly class Pong implements Event {}
