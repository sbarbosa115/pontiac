<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\AccountContext;
use App\Entity\Payment;
use App\Payment\WompiKeys;
use App\Payment\WompiSignature;
use App\Repository\WompiSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Local development only: sends our own webhook the event Wompi would send for a payment, signed with the
 * consultant's events secret, so the whole path (checksum, reference, amount, approval, emails) runs without a
 * public URL or real keys.
 */
#[AsCommand(name: 'app:wompi:simulate-event', description: 'Sends the local webhook a signed Wompi event for a payment (development only).')]
final class SimulateWompiEventCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountContext $context,
        private readonly WompiSettingsRepository $wompi,
        private readonly WompiKeys $keys,
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Our reference, "PON-…" (the result page and Pagos show it)')] string $reference,
        #[Option('APPROVED, DECLINED, VOIDED or ERROR')] string $status = 'APPROVED',
        #[Option('Wompi\'s payment method type')] string $method = 'CARD',
        #[Option('Where the app answers inside Docker')] string $url = 'http://nginx',
    ): int {
        if ('prod' === $this->environment) {
            $io->error('Not in production: there, Wompi sends the real events.');

            return Command::FAILURE;
        }
        $this->context->enterPlatformScope();
        $payment = $this->em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);
        $account = $payment?->getAccount();
        if (null === $payment || null === $account) {
            $io->error(sprintf('No payment "%s".', $reference));

            return Command::FAILURE;
        }
        $this->context->enterAccount($account);
        $settings = $this->wompi->current();
        if (null === $settings || !$settings->isConfigured()) {
            $io->error('The consultant has no Wompi keys (Ajustes › Pagos Wompi).');

            return Command::FAILURE;
        }

        $event = [
            'event' => 'transaction.updated',
            'data' => ['transaction' => [
                'id' => 'sim-'.bin2hex(random_bytes(6)),
                'amount_in_cents' => $payment->getAmountInCents(),
                'reference' => $payment->getReference(),
                'currency' => $payment->getCurrency(),
                'payment_method_type' => $method,
                'status' => strtoupper($status),
            ]],
            'environment' => 'test',
            'signature' => ['properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents']],
            'timestamp' => time(),
            'sent_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];
        $event['signature']['checksum'] = WompiSignature::checksum($event, $this->keys->secret($settings, 'eventsSecret'));

        $response = $this->httpClient->request('POST', rtrim($url, '/').'/webhooks/wompi/'.$account->getId(), ['json' => $event]);
        $io->success(sprintf('%s → %d %s', $reference, $response->getStatusCode(), $response->getContent(false)));

        return Command::SUCCESS;
    }
}
