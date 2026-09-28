<?php

declare(strict_types=1);

namespace App\Payment;

use App\Api\ApiException;
use App\Entity\Account;
use App\Entity\Enrollment;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\EnrollmentStatus;
use App\Enum\PaymentStatus;
use App\Enum\FlowTrigger;
use App\Flow\ContactMoment;
use App\Mail\PaymentMailer;
use App\Portal\PortalAccess;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies a payment's result, from wherever it comes (Wompi's event, Wompi's API asked by the result page, or the
 * owner recording money received): once, under a lock per payment. An approved payment activates its plan and makes
 * the person a client (a paid plan only), both sides are told, and a first paid plan invites them to the portal.
 */
final class PaymentApplier
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LockFactory $locks,
        private readonly PaymentMailer $mailer,
        private readonly PortalAccess $portal,
        private readonly EventDispatcherInterface $events,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Wompi's transaction for this payment. Its reference, amount and currency must be ours: anything else marks the
     * payment `error` and approves nothing.
     *
     * @param array<string, mixed> $transaction Wompi's transaction object
     *
     * @return bool whether the payment changed
     */
    public function fromWompi(Account $account, Payment $payment, array $transaction, \DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        $lock = $this->locks->createLock('payment-'.$payment->getId());
        $lock->acquire(true);
        try {
            // Another request may have settled it meanwhile.
            $this->em->refresh($payment);
            if (PaymentStatus::Pending !== $payment->getStatus()) {
                return false;
            }
            $transactionId = (string) ($transaction['id'] ?? '');
            $matches = ($transaction['reference'] ?? null) === $payment->getReference()
                && ($transaction['amount_in_cents'] ?? null) === $payment->getAmountInCents()
                && ($transaction['currency'] ?? null) === $payment->getCurrency();
            $status = $matches ? PaymentStatus::fromWompi((string) ($transaction['status'] ?? '')) : PaymentStatus::Error;
            if (!$matches) {
                $this->logger->warning('Wompi transaction {id} does not match payment {reference}.', ['id' => $transactionId, 'reference' => $payment->getReference()]);
            }
            if (PaymentStatus::Pending === $status) {
                $payment->track($transactionId);
                $this->em->flush();

                return false;
            }
            $method = \is_string($transaction['payment_method_type'] ?? null) ? $transaction['payment_method_type'] : null;
            $payment->settle($status, $transactionId, $method, $transaction, $now);
            $approved = PaymentStatus::Approved === $status;
            if ($approved) {
                $this->approve($payment->getEnrollment());
            }
            $this->em->flush();
        } finally {
            $lock->release();
        }

        if ($approved) {
            $this->mailer->received($account, $payment);
            $this->welcome($account, $payment);
        }

        return true;
    }

    /** Money the owner received outside Wompi, for a plan waiting for its payment. */
    public function recordManual(Account $account, Enrollment $enrollment, string $method, ?string $note, User $by, \DateTimeImmutable $paidAt): Payment
    {
        if (EnrollmentStatus::PendingPayment !== $enrollment->getStatus() || $enrollment->isFree()) {
            throw ApiException::conflict('enrollment_not_payable', 'This plan is not waiting for a payment.');
        }
        $payment = Payment::manual($enrollment, $method, $note, $by, $paidAt);
        $this->em->persist($payment);
        $this->approve($enrollment);
        $this->em->flush();
        $this->mailer->received($account, $payment);
        $this->welcome($account, $payment);

        return $payment;
    }

    /** A first paid plan opens the portal: "Accede a tu portal". */
    private function welcome(Account $account, Payment $payment): void
    {
        if (!$payment->getEnrollment()->isFree()) {
            $this->portal->inviteAfterPayment($account, $payment->getContact());
        }
        $this->events->dispatch(new ContactMoment($account, $payment->getContact(), FlowTrigger::PaymentApproved));
    }

    private function approve(Enrollment $enrollment): void
    {
        $enrollment->activate();
        if (!$enrollment->isFree()) {
            $enrollment->getContact()->becomeClient();
        }
    }
}
