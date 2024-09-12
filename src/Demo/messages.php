<?php

declare(strict_types=1);

namespace Telephantast\Demo;

use Telephantast\Message\Event;
use Telephantast\Message\Message;

/**
 * @implements Message<void>
 */
final class Ping implements Message {}

final class Pong implements Event {}
