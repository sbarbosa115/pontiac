<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiResponse;
use App\Api\Input\MePreferencesInput;
use App\Api\InputMapper;
use App\Api\Output\MePreferencesOutput;
use App\Entity\User;
use App\Enum\UiTheme;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The signed-in person's own preferences, whatever their role. They belong to the person at the screen: while a
 * super admin acts as someone (X-Switch-User), this saves the super admin's, never the impersonated user's.
 */
final class MePreferencesController extends AbstractController
{
    /** {uiTheme?}: "light", "dark" or "system". */
    #[Route('/api/me/preferences', name: 'api_me_preferences', methods: ['PATCH'])]
    #[ApiResponse(MePreferencesOutput::class)]
    public function __invoke(#[CurrentUser] User $user, Security $security, Request $request, InputMapper $input, EntityManagerInterface $em): JsonResponse
    {
        $token = $security->getToken();
        $original = $token instanceof SwitchUserToken ? $token->getOriginalToken()->getUser() : null;
        $person = $original instanceof User ? $original : $user;

        $preferences = $input->map($input->json($request), MePreferencesInput::class);
        if (null !== $preferences->uiTheme) {
            $person->setUiTheme(UiTheme::from($preferences->uiTheme));
        }
        $em->flush();

        return $this->json(new MePreferencesOutput(uiTheme: $person->uiTheme()->value));
    }
}
