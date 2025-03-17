<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Debug;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Telephantast\Message\Message;
use Telephantast\MessageBus\Handler;

/**
 * @api
 */
#[AsCommand(name: 'telephantast:debug')]
final class DebugConsoleCommand extends Command
{
    /**
     * @param array<class-string<Message>, non-empty-array<int|'publish', Definition|Reference>>  $messageBusHandlers
     * @param array<non-empty-string, non-empty-array<class-string<Message>, non-empty-list<Definition|Reference>>> $queueToConsumerHandlers
     * */
    public function __construct(
        private array $messageBusHandlers = [],
        private array $queueToConsumerHandlers = [],
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {


        $table = new Table($output);
        $table
            ->setHeaders(['Event', 'handler']);
        $messagesToHandlers = [];
        foreach ($this->messageBusHandlers as $messageClass => $messageBusHandlers) {
            $handlersInformation = '';
            /** @var array<class-string<Message>, Handler> $messageBusHandlers */
            foreach ($messageBusHandlers as $_s => $handler) {

                $handlersInformation .=  $handler->id() . PHP_EOL;
            }
            if (!\array_key_exists($messageClass, $messagesToHandlers)) {
                $messagesToHandlers[$messageClass] = $handlersInformation;
            } else {
                $messagesToHandlers[$messageClass] .= $handlersInformation;
            }


        }
        foreach ($this->queueToConsumerHandlers as $queue => $info) {
            foreach ($info as $messageClass => $handlers) {
                $handlersInformation = '';
                /** @var array<class-string<Message>, Handler> $handlers */
                foreach ($handlers as $handler) {

                    $handlersInformation .=  '[Async] [' . $queue . '] ' . $handler->id() . PHP_EOL;
                }
                if (!\array_key_exists($messageClass, $messagesToHandlers)) {
                    $messagesToHandlers[$messageClass] = $handlersInformation;
                } else {
                    $messagesToHandlers[$messageClass] .= $handlersInformation;
                }
            }
        }
        foreach ($messagesToHandlers as $messageClass => $handlers) {
            $table->addRow([
                $messageClass, $handlers,
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
