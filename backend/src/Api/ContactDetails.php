<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Output\ContactDetailOutput;
use App\Entity\Account;
use App\Entity\Contact;
use App\Api\Output\PortalAccessOutput;
use App\Flow\FlowViews;
use App\Payment\PaymentLinks;
use App\Portal\PortalAccess;
use App\Repository\UserRepository;
use App\Repository\BookingSessionRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\LeadSubmissionRepository;
use App\Repository\PaymentRepository;

/** A contact's page in one answer: who they are, what they sent, their sessions, their plans and payments. */
final class ContactDetails
{
    public function __construct(
        private readonly LeadSubmissionRepository $submissions,
        private readonly BookingSessionRepository $sessions,
        private readonly EnrollmentRepository $enrollments,
        private readonly PaymentRepository $payments,
        private readonly PaymentLinks $links,
        private readonly UserRepository $users,
        private readonly PortalAccess $portal,
        private readonly FlowViews $flows,
    ) {
    }

    public function of(Account $account, Contact $contact): ContactDetailOutput
    {
        $enrollments = $this->enrollments->findForContact($contact);
        $counts = $this->sessions->countsByEnrollment($enrollments);
        $payments = [];
        foreach ($this->payments->findForContact($contact) as $payment) {
            $payments[(string) $payment->getEnrollment()->getId()][] = $payment;
        }

        $login = $this->users->findClientOf($contact);

        return Presenter::contactDetail(
            $contact,
            $this->submissions->findForContact($contact),
            $this->sessions->findForContact($contact),
            array_map(fn ($e) => Presenter::enrollment($e, $counts[(string) $e->getId()], $this->links->url($account, $e), $payments[(string) $e->getId()] ?? []), $enrollments),
            new PortalAccessOutput(status: $this->portal->status($login), lastSignInAt: Presenter::timestamp($login?->getLastSignInAt())),
            $this->flows->ofContact($contact),
        );
    }
}
