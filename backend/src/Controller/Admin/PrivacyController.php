<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\PrivacyInput;
use App\Api\InputMapper;
use App\Api\Output\PrivacyOutput;
use App\Entity\Account;
use App\Entity\User;
use App\Repository\PlatformSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Ajustes › Privacidad: the policy the consultant's forms link to (/<consultant>/privacidad). */
#[Route('/api/admin/privacy', name: 'api_admin_privacy_')]
final class PrivacyController extends ApiController
{
    public function __construct(private readonly PlatformSettingsRepository $settings)
    {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    #[ApiResponse(PrivacyOutput::class)]
    public function show(): JsonResponse
    {
        return $this->json($this->output($this->account()));
    }

    /** The owner answers for the data (Ley 1581): only they change it. Empty goes back to the platform's text. */
    #[Route('', name: 'update', methods: ['PUT'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(PrivacyOutput::class)]
    public function update(Request $request, InputMapper $input, EntityManagerInterface $em): JsonResponse
    {
        $data = $input->map($input->json($request), PrivacyInput::class);
        $account = $this->account()->setPrivacyText(trim((string) $data->text));
        $em->flush();

        return $this->json($this->output($account));
    }

    private function output(Account $account): PrivacyOutput
    {
        $default = $this->settings->current()->getDefaultPrivacyText();

        return new PrivacyOutput(
            text: '' !== $account->getPrivacyText() ? $account->getPrivacyText() : $default,
            usingDefault: '' === $account->getPrivacyText(),
            defaultText: $default,
        );
    }
}
