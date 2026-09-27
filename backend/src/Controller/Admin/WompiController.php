<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\WompiSettingsInput;
use App\Api\InputMapper;
use App\Api\Output\WompiSettingsOutput;
use App\Api\Output\WompiTestOutput;
use App\Api\Presenter;
use App\Entity\User;
use App\Entity\WompiSettings;
use App\Enum\AccountFeature;
use App\Payment\WompiClient;
use App\Payment\WompiKeys;
use App\Repository\WompiSettingsRepository;
use App\Security\RequiresFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Ajustes › Pagos Wompi: the owner's keys. Secrets go in and never come out. */
#[Route('/api/admin/wompi', name: 'api_admin_wompi_')]
#[IsGranted(User::ROLE_OWNER)]
#[RequiresFeature(AccountFeature::Payments)]
final class WompiController extends ApiController
{
    public function __construct(
        private readonly WompiSettingsRepository $settings,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    #[ApiResponse(WompiSettingsOutput::class)]
    public function show(): JsonResponse
    {
        return $this->json($this->present($this->settings->current() ?? new WompiSettings($this->account())));
    }

    #[Route('', name: 'update', methods: ['PUT'])]
    #[ApiResponse(WompiSettingsOutput::class)]
    public function update(Request $request, InputMapper $input, WompiKeys $keys, EntityManagerInterface $em): JsonResponse
    {
        $data = $input->map($input->json($request), WompiSettingsInput::class);
        $settings = $keys->apply($this->settings->forAccount($this->account()), $data);
        $em->flush();

        return $this->json($this->present($settings));
    }

    /** "Probar conexión": does Wompi know the public key? */
    #[Route('/test', name: 'test', methods: ['POST'])]
    #[ApiResponse(WompiTestOutput::class)]
    public function test(WompiClient $client): JsonResponse
    {
        $settings = $this->settings->current();
        $mode = $settings?->getMode();
        if (null === $settings || null === $mode) {
            throw ApiException::conflict('payments_not_configured', 'Save the public key first.');
        }
        $merchant = $client->merchant($settings->getPublicKey()) ?? throw ApiException::conflict('wompi_rejected', 'Wompi did not accept this public key (or could not be reached).');

        return $this->json(new WompiTestOutput(merchantName: (string) ($merchant['name'] ?? $merchant['legal_name'] ?? ''), mode: $mode));
    }

    private function present(WompiSettings $settings): WompiSettingsOutput
    {
        return Presenter::wompiSettings($settings, rtrim($this->appUrl, '/').'/webhooks/wompi/'.$this->account()->getId());
    }
}
