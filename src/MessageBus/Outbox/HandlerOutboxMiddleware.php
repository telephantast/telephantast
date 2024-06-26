<?php

declare(strict_types=1);

namespace Telephantast\MessageBus\Outbox;

use Telephantast\MessageBus\Async\Publish;
use Telephantast\MessageBus\Handler\Pipeline;
use Telephantast\MessageBus\MessageContext;
use Telephantast\MessageBus\Middleware;
use Telephantast\MessageBus\Transaction\TransactionProvider;

/**
 * @api
 */
final readonly class HandlerOutboxMiddleware implements Middleware
{
    public function __construct(
        private OutboxStorage $outboxStorage,
        private TransactionProvider $transactionProvider,
        private Publish $publish,
    ) {}

    public function handle(MessageContext $messageContext, Pipeline $pipeline): mixed
    {
        if ($messageContext->hasAttribute(Outbox::class)) {
            return $pipeline->continue();
        }

        $messageId = $messageContext->getMessageId();
        $outbox = new Outbox();
        $messageContext->setAttribute($outbox);

        $result = $this->transactionProvider->wrapInTransaction(function () use ($pipeline, $messageId, $outbox): mixed {
            $result = $pipeline->continue();

            if ($outbox->envelopes !== []) {
                $this->outboxStorage->save(null, $messageId, $outbox);
            }

            return $result;
        });

        if ($outbox->envelopes !== []) {
            $this->publish->publish($outbox->envelopes);
            $this->outboxStorage->save(null, $messageId, new Outbox());
        }

        return $result;
    }
}
