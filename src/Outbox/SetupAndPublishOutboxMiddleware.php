<?php

declare(strict_types=1);

namespace Telephantast\Outbox;

use Telephantast\MessageBus\Envelope;
use Telephantast\MessageBus\Handler\Pipeline;
use Telephantast\MessageBus\MessageContext;
use Telephantast\MessageBus\Middleware;
use function Telephantast\MessageBus\MessageId\messageId;

/**
 * @api
 */
final readonly class SetupAndPublishOutboxMiddleware implements Middleware
{
    public function __construct(
        private OutboxRepository $outboxRepository,
        private OutboxPublish $publish,
    ) {}

    public function handle(MessageContext $messageContext, Pipeline $pipeline): mixed
    {
        $outbox = $messageContext->parent?->attribute(Outbox::class);

        if ($outbox !== null) {
            $messageContext->setAttribute($outbox);

            return $pipeline->continue();
        }

        $outboxId = messageId($messageContext);
        $messageContext->setAttribute(new Outbox($outboxId));

        $result = $pipeline->continue();

        $envelopes = $this->outboxRepository->get($outboxId);

        if ($envelopes !== []) {
            $this->publish->publish($envelopes, function (Envelope $envelope): void {
                $this->outboxRepository->remove(messageId($envelope));
            });
        }

        return $result;
    }
}
