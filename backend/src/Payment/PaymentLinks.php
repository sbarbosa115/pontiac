<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Account;
use App\Entity\Enrollment;
use App\Enum\AccountFeature;
use App\Enum\EnrollmentStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** A plan's payment link (/<consultant>/pagar/<token>), while it waits for a payment. */
final class PaymentLinks
{
    public function __construct(#[Autowire('%env(APP_URL)%')] private readonly string $appUrl)
    {
    }

    public function url(Account $account, Enrollment $enrollment): ?string
    {
        $token = $enrollment->getPaymentToken();
        if (null === $token || EnrollmentStatus::PendingPayment !== $enrollment->getStatus() || !$account->hasFeature(AccountFeature::Payments)) {
            return null;
        }

        return rtrim($this->appUrl, '/').'/'.$account->getSlug().'/pagar/'.$token;
    }
}
