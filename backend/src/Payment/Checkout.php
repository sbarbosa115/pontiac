<?php

declare(strict_types=1);

namespace App\Payment;

use App\Api\ApiException;
use App\Entity\Account;
use App\Entity\Enrollment;
use App\Entity\Payment;
use App\Enum\AccountFeature;
use App\Enum\EnrollmentStatus;
use App\Repository\WompiSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sends someone to Wompi's Web Checkout to pay a plan: a pending payment with our reference, and the checkout URL
 * with its integrity signature. Wompi brings them back to /<consultant>/pago/<reference>.
 */
final class Checkout
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WompiSettingsRepository $wompi,
        private readonly WompiKeys $keys,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    /** Whether this consultant can take payments now: the feature on and Wompi set up. */
    public function isAvailable(Account $account): bool
    {
        return $account->hasFeature(AccountFeature::Payments) && true === $this->wompi->current()?->isConfigured();
    }

    /**
     * @return string the checkout URL to redirect to
     */
    public function start(Account $account, Enrollment $enrollment): string
    {
        $settings = $this->wompi->current();
        if (!$account->hasFeature(AccountFeature::Payments) || null === $settings || !$settings->isConfigured()) {
            throw ApiException::conflict('payments_not_configured', 'This consultant does not take online payments yet.');
        }
        if (EnrollmentStatus::PendingPayment !== $enrollment->getStatus() || $enrollment->isFree()) {
            throw ApiException::conflict('enrollment_not_payable', 'This plan is not waiting for a payment.');
        }

        $payment = Payment::forCheckout($enrollment);
        $this->em->persist($payment);
        $this->em->flush();

        $contact = $enrollment->getContact();
        $query = [
            'public-key' => $settings->getPublicKey(),
            'currency' => $payment->getCurrency(),
            'amount-in-cents' => $payment->getAmountInCents(),
            'reference' => $payment->getReference(),
            'signature:integrity' => WompiSignature::integrity($payment->getReference(), $payment->getAmountInCents(), $payment->getCurrency(), $this->keys->secret($settings, 'integritySecret')),
            'redirect-url' => $this->resultUrl($account, $payment),
        ];
        if (!$contact->isAnonymized()) {
            $query['customer-data:email'] = $contact->getEmail();
            $query['customer-data:full-name'] = $contact->getFullName();
        }

        return WompiClient::CHECKOUT_URL.'?'.http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }

    public function resultUrl(Account $account, Payment $payment): string
    {
        return rtrim($this->appUrl, '/').'/'.$account->getSlug().'/pago/'.$payment->getReference();
    }
}
