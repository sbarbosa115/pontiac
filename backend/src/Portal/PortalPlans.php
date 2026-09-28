<?php

declare(strict_types=1);

namespace App\Portal;

use App\Api\Output\PortalPlanOutput;
use App\Api\Presenter;
use App\Entity\Account;
use App\Entity\Contact;
use App\Enum\EnrollmentStatus;
use App\Payment\Checkout;
use App\Repository\BookingSessionRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\PaymentRepository;

/** A client's plans with their progress and payments, as the portal shows them. */
final class PortalPlans
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments,
        private readonly BookingSessionRepository $sessions,
        private readonly PaymentRepository $payments,
        private readonly Checkout $checkout,
    ) {
    }

    /**
     * @return list<PortalPlanOutput> newest first
     */
    public function of(Account $account, Contact $contact): array
    {
        $enrollments = $this->enrollments->findForContact($contact);
        $counts = $this->sessions->countsByEnrollment($enrollments);
        $payments = [];
        foreach ($this->payments->findForContact($contact) as $payment) {
            $payments[(string) $payment->getEnrollment()->getId()][] = $payment;
        }
        $canPay = $this->checkout->isAvailable($account);

        return array_map(static fn ($e) => Presenter::portalPlan(
            $e,
            $counts[(string) $e->getId()],
            $canPay && EnrollmentStatus::PendingPayment === $e->getStatus() && !$e->isFree(),
            $payments[(string) $e->getId()] ?? [],
        ), $enrollments);
    }
}
