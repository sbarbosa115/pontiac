<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\AccountContext;
use App\Payment\PaymentApplier;
use App\Payment\WompiKeys;
use App\Payment\WompiSignature;
use App\Repository\AccountRepository;
use App\Repository\PaymentRepository;
use App\Repository\WompiSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Wompi's events for one consultant's merchant (the URL they paste in Wompi's dashboard, Ajustes › Pagos Wompi).
 * Only an event whose checksum matches the consultant's events secret is read; a transaction update is applied to
 * our payment with the same reference, once. Anything we do not act on still answers 200, so Wompi stops retrying.
 */
final class WompiWebhookController
{
    #[Route('/webhooks/wompi/{accountId}', name: 'wompi_events', requirements: ['accountId' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(
        string $accountId,
        Request $request,
        AccountRepository $accounts,
        AccountContext $context,
        WompiSettingsRepository $wompi,
        WompiKeys $keys,
        PaymentRepository $payments,
        PaymentApplier $applier,
        LoggerInterface $logger,
    ): JsonResponse {
        $account = $accounts->find($accountId);
        if (null === $account) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }
        // Money that arrives is recorded even for a suspended consultant.
        $context->enterAccount($account);
        $settings = $wompi->current();
        $event = json_decode($request->getContent(), true);
        if (null === $settings || !$settings->isConfigured() || !\is_array($event) || !WompiSignature::isAuthentic($event, $keys->secret($settings, 'eventsSecret'))) {
            $logger->warning('Rejected a Wompi event for account {account}: bad checksum or no settings.', ['account' => $accountId]);

            return new JsonResponse(['error' => 'invalid_signature'], 401);
        }

        $transaction = $event['data']['transaction'] ?? null;
        if ('transaction.updated' !== ($event['event'] ?? null) || !\is_array($transaction)) {
            return new JsonResponse(['status' => 'ignored']);
        }
        $payment = $payments->findOneByReference((string) ($transaction['reference'] ?? ''));
        if (null === $payment) {
            $logger->notice('A Wompi event for unknown reference {reference}.', ['reference' => $transaction['reference'] ?? '']);

            return new JsonResponse(['status' => 'ignored']);
        }

        return new JsonResponse(['status' => $applier->fromWompi($account, $payment, $transaction) ? 'applied' : 'unchanged']);
    }
}
