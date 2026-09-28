<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Account;
use App\Entity\OutgoingEmail;
use App\Enum\EmailStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Records every attempt to send an email in outgoing_email (Correos): sent, or failed and why.
 *
 * The mailer announces a message twice when it goes through the queue: once as it is queued, once as the worker sends
 * it. Only the send counts. Just before it, the EmailTag headers are taken off the message (they are for us, not the
 * recipient) and kept until the send ends, one way or the other.
 *
 * Written with DBAL, not the entity manager: a log line must never flush someone else's pending changes.
 */
final class EmailLog
{
    /** @var \WeakMap<RawMessage, array{kind: string, account: string|null}> */
    private \WeakMap $tags;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->tags = new \WeakMap();
    }

    #[AsEventListener(event: MessageEvent::class, priority: -1000)]
    public function onSending(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if ($event->isQueued() || !$message instanceof Email) {
            return;
        }

        $headers = $message->getHeaders();
        $account = $headers->get(EmailTag::ACCOUNT_HEADER)?->getBodyAsString();
        $this->tags[$message] = [
            'kind' => $headers->get(EmailTag::KIND_HEADER)?->getBodyAsString() ?? 'other',
            'account' => null !== $account && Uuid::isValid($account) ? $account : null,
        ];
        $headers->remove(EmailTag::KIND_HEADER);
        $headers->remove(EmailTag::ACCOUNT_HEADER);
    }

    #[AsEventListener(event: SentMessageEvent::class)]
    public function onSent(SentMessageEvent $event): void
    {
        $this->record($event->getMessage()->getOriginalMessage(), EmailStatus::Sent, null);
    }

    #[AsEventListener(event: FailedMessageEvent::class)]
    public function onFailed(FailedMessageEvent $event): void
    {
        $this->record($event->getMessage(), EmailStatus::Failed, $event->getError()->getMessage());
    }

    private function record(RawMessage $message, EmailStatus $status, ?string $error): void
    {
        if (!$message instanceof Email) {
            return;
        }

        $tag = $this->tags[$message] ?? ['kind' => 'other', 'account' => null];
        $log = new OutgoingEmail(
            // A reference: no query, and the row only needs its id.
            null === $tag['account'] ? null : $this->em->getReference(Account::class, Uuid::fromString($tag['account'])),
            $tag['kind'],
            implode(', ', array_map(static fn (Address $a) => $a->getAddress(), $message->getTo())),
            (string) $message->getSubject(),
            $status,
            $error,
        );
        $this->em->getConnection()->insert('outgoing_email', $log->toRow());
    }
}
