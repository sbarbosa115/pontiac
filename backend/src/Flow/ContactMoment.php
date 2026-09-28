<?php

declare(strict_types=1);

namespace App\Flow;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\LandingPage;
use App\Enum\FlowTrigger;

/**
 * Something happened to a person that flows may follow: they sent a form, booked, had a session, paid, finished.
 * Dispatched after the change is saved, where it happens (LeadIntake, Booker, PaymentApplier, Enrollments); FlowEngine
 * listens. The page, when it came from one, may put them in the flow it feeds.
 */
final readonly class ContactMoment
{
    public function __construct(
        public Account $account,
        public Contact $contact,
        public FlowTrigger $trigger,
        public ?LandingPage $page = null,
    ) {
    }
}
