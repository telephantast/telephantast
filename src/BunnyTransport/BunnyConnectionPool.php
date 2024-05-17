<?php

declare(strict_types=1);

namespace Telephantast\BunnyTransport;

use Bunny\Async\Client;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use function React\Async\await;

/**
 * @api
 */
final class BunnyConnectionPool
{
    /**
     * @var null|Client|PromiseInterface<Client>
     */
    private null|Client|PromiseInterface $client = null;

    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 5672,
        private readonly string $user = 'guest',
        private readonly string $password = 'guest',
        private readonly string $vhost = '/',
        private readonly int $heartbeatSeconds = 60,
    ) {}

    /**
     * @psalm-suppress MissingThrowsDocblock
     */
    public function get(): Client
    {
        if ($this->client instanceof Client && $this->client->isConnected()) {
            return $this->client;
        }

        if (!$this->client instanceof PromiseInterface) {
            $client = new Client(Loop::get(), [
                'host' => $this->host,
                'port' => $this->port,
                'user' => $this->user,
                'password' => $this->password,
                'vhost' => $this->vhost,
                'heartbeat' => $this->heartbeatSeconds,
            ]);
            $this->client = $client->connect()->then(fn(): Client => $this->client = $client);
        }

        return await($this->client);
    }

    /**
     * @psalm-suppress MissingThrowsDocblock
     */
    public function disconnect(): void
    {
        if ($this->client === null) {
            return;
        }

        $clientToDisconnect = $this->client;
        $this->client = null;

        if ($clientToDisconnect instanceof PromiseInterface) {
            $clientToDisconnect = await($clientToDisconnect);
        }

        await($clientToDisconnect->disconnect());
    }
}
