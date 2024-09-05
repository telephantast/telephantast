<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Async;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Telephantast\Message\Message;
use Telephantast\MessageBus\Async\ExchangeResolver;
use Telephantast\MessageBus\Async\TransportSetup;

/**
 * @internal
 * @psalm-internal Telephantast\TelephantastBundle
 */
final class SetupConsoleCommand extends Command
{
    /**
     * @psalm-suppress PossiblyUnusedMethod
     * @param array<class-string<Message>, list<non-empty-string>> $messageClassesToQueues
     */
    public function __construct(
        private readonly TransportSetup $transportSetup,
        private readonly ExchangeResolver $exchangeResolver,
        private readonly array $messageClassesToQueues,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exchangeToQueues = [];

        foreach ($this->messageClassesToQueues as $messageClass => $queues) {
            $exchangeToQueues[$this->exchangeResolver->resolve($messageClass)] = $queues;
        }

        $this->transportSetup->setup($exchangeToQueues);

        return self::SUCCESS;
    }
}
