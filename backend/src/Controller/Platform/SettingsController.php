<?php

declare(strict_types=1);

namespace App\Controller\Platform;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\PlatformSettingsInput;
use App\Api\InputMapper;
use App\Api\Output\PlatformSettingsOutput;
use App\Api\Output\SettingsChangeOutput;
use App\Api\Presenter;
use App\Platform\PlatformSettingsEditor;
use App\Repository\PlatformSettingsChangeRepository;
use App\Repository\PlatformSettingsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Plataforma › Configuración: Pontiac's own settings, and who changed them. */
#[Route('/api/platform/settings', name: 'api_platform_settings_')]
final class SettingsController extends ApiController
{
    #[Route('', name: 'show', methods: ['GET'])]
    #[ApiResponse(PlatformSettingsOutput::class)]
    public function show(PlatformSettingsRepository $settings): JsonResponse
    {
        return $this->json(Presenter::platformSettings($settings->current()));
    }

    /** A field left out is left as it is; the change is logged (Historial). */
    #[Route('', name: 'update', methods: ['PATCH'])]
    #[ApiResponse(PlatformSettingsOutput::class)]
    public function update(Request $request, InputMapper $input, PlatformSettingsEditor $editor): JsonResponse
    {
        $data = $input->map($input->json($request), PlatformSettingsInput::class);

        return $this->json(Presenter::platformSettings($editor->apply($data, $this->appUser())));
    }

    /** Newest first; ?q= searches who changed it and the settings' names. */
    #[Route('/history', name: 'history', methods: ['GET'])]
    #[ApiResponse(SettingsChangeOutput::class, page: true)]
    public function history(Request $request, PlatformSettingsChangeRepository $changes): JsonResponse
    {
        return $this->page($changes->search($request->query->getString('q'), $this->pagination($request)), Presenter::settingsChange(...));
    }
}
