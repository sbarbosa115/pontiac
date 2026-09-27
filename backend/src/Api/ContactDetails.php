<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Output\ContactDetailOutput;
use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\Payment;
use App\Payment\PaymentLinks;
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

        return Presenter::contactDetail(
            $contact,
            $this->submissions->findForContact($contact),
            $this->sessions->findForContact($contact),
            array_map(fn ($e) => Presenter::enrollment($e, $counts[(string) $e->getId()], $this->links->url($account, $e), $payments[(string) $e->getId()] ?? []), $enrollments),
        );
    }
}
