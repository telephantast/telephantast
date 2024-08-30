<?php

declare(strict_types=1);

namespace Telephantast\Demo;

use Telephantast\Message\Event;
use Telephantast\Message\Message;

/**
 * @psalm-immutable
 * @implements Message<void>
 */
final class Ping implements Message {}

/**
 * @psalm-immutable
 */
final class Pong implements Event {}
