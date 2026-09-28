<?php

declare(strict_types=1);

namespace App\Payment;

use App\Api\ApiException;
use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\Enrollment;
use App\Entity\Plan;
use App\Enum\AccountFeature;
use App\Enum\EnrollmentOutcome;
use App\Enum\EnrollmentStatus;
use App\Enum\FlowTrigger;
use App\Flow\ContactMoment;
use App\Mail\PaymentMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A person's plans, as the consultant runs them: assign one (a paid one emails its payment link), send the link
 * again, cancel one still waiting for payment, and once a plan is used up, renew it with another or finish the
 * consultancy.
 */
final class Enrollments
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentMailer $mailer,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    public function assign(Account $account, Contact $contact, Plan $plan): Enrollment
    {
        if (!$plan->isActive()) {
            throw ApiException::unprocessable('plan_inactive', 'This plan is disabled: enable it in Planes first.');
        }
        if ($contact->isAnonymized()) {
            throw ApiException::conflict('already_anonymized', 'This person\'s data was erased.');
        }
        if (!$plan->isFree() && !$account->hasFeature(AccountFeature::Payments)) {
            throw ApiException::forbidden('feature_disabled', 'Payments are not enabled for this consultant.');
        }
        $enrollment = new Enrollment($contact, $plan, null);
        $this->em->persist($enrollment);
        $this->em->flush();
        if (!$enrollment->isFree()) {
            $this->mailer->paymentLink($account, $enrollment);
        }

        return $enrollment;
    }

    public function sendLink(Account $account, Enrollment $enrollment): void
    {
        if (EnrollmentStatus::PendingPayment !== $enrollment->getStatus() || $enrollment->isFree()) {
            throw ApiException::conflict('enrollment_not_payable', 'This plan is not waiting for a payment.');
        }
        $this->mailer->paymentLink($account, $enrollment);
    }

    public function cancel(Enrollment $enrollment): Enrollment
    {
        try {
            $enrollment->cancel();
        } catch (\DomainException $e) {
            throw ApiException::conflict('enrollment_not_cancellable', $e->getMessage());
        }
        $this->em->flush();

        return $enrollment;
    }

    /** "Renovar": this plan is done; another starts (a paid one sends its payment link). */
    public function renew(Account $account, Enrollment $enrollment, Plan $plan): Enrollment
    {
        $this->conclude($enrollment, EnrollmentOutcome::Renewed);

        return $this->assign($account, $enrollment->getContact(), $plan);
    }

    /** "Finalizar asesoría": this plan is done and so is the consultancy. */
    public function finish(Enrollment $enrollment): Enrollment
    {
        $this->conclude($enrollment, EnrollmentOutcome::Finished);
        $enrollment->getContact()->finish();
        $this->em->flush();
        $account = $enrollment->getAccount() ?? throw new \LogicException('An enrollment belongs to an account.');
        $this->events->dispatch(new ContactMoment($account, $enrollment->getContact(), FlowTrigger::ConsultancyFinished));

        return $enrollment;
    }

    private function conclude(Enrollment $enrollment, EnrollmentOutcome $outcome): void
    {
        try {
            $enrollment->conclude($outcome);
        } catch (\DomainException $e) {
            throw ApiException::conflict('enrollment_not_completed', $e->getMessage());
        }
    }
}
