<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Async;

use Psr\Container\ContainerInterface;
use Revolt\EventLoop\UnsupportedFeatureException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Telephantast\MessageBus\Async\Consumer;
use Telephantast\MessageBus\Async\TransportConsume;
use function Amp\trapSignal;

/**
 * @internal
 * @psalm-internal Telephantast\TelephantastBundle
 */
final class ConsumeConsoleCommand extends Command
{
    /**
     * @psalm-suppress PossiblyUnusedMethod
     * @param ContainerInterface<Consumer> $queueToConsumer
     */
    public function __construct(
        private readonly TransportConsume $transportConsume,
        private readonly ContainerInterface $queueToConsumer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('queues', InputArgument::REQUIRED | InputArgument::IS_ARRAY);
    }

    /**
     * @throws UnsupportedFeatureException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var non-empty-list<non-empty-string> */
        $queues = $input->getArgument('queues');

        foreach ($queues as $queue) {
            $this->transportConsume->runConsumer($this->queueToConsumer->get($queue));
        }

        trapSignal([SIGINT, SIGTERM]);

        $this->transportConsume->disconnect();

        return self::SUCCESS;
    }
}
