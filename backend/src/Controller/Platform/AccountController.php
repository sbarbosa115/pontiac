<?php

declare(strict_types=1);

namespace App\Controller\Platform;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\AccountCreateInput;
use App\Api\Input\AccountUpdateInput;
use App\Api\InputMapper;
use App\Api\Output\AccountDetailOutput;
use App\Api\Output\AccountSummaryOutput;
use App\Api\Output\TeamMemberOutput;
use App\Api\Presenter;
use App\Entity\Account;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Platform\AccountCreator;
use App\Platform\AccountSlugPolicy;
use App\Repository\AccountRepository;
use App\Repository\UserRepository;
use App\Security\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Plataforma › Asesores: the consultants (accounts), created, changed, suspended and reactivated by a super admin,
 * and their owner and assistants.
 */
#[Route('/api/platform/accounts', name: 'api_platform_account_')]
final class AccountController extends ApiController
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly UserRepository $users,
        private readonly InvitationService $invitations,
        private readonly InputMapper $input,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches name, address and owner's email; ?status= "active" or "suspended". */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(AccountSummaryOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        $active = match ($request->query->getString('status')) {
            '' => null,
            'active' => true,
            'suspended' => false,
            default => throw ApiException::badRequest('invalid_filter', 'Unknown value for "status".'),
        };
        $page = $this->accounts->search($request->query->getString('q'), $active, $this->pagination($request));
        /** @var list<Account> $accounts */
        $accounts = $page->items;
        $owners = $this->users->findOwnersOf($accounts);
        $people = $this->users->countPeopleOf($accounts);

        return $this->page($page, static fn (object $account) => Presenter::accountSummary(
            $account,
            $owners[(string) $account->getId()] ?? null,
            $people[(string) $account->getId()],
        ));
    }

    /** A new consultant: the account with the platform's defaults, and its owner, invited by email. */
    #[Route('', name: 'create', methods: ['POST'])]
    #[ApiResponse(AccountDetailOutput::class, status: 201)]
    public function create(Request $request, AccountCreator $creator): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), AccountCreateInput::class);
        $account = $creator->create((string) $data->name, (string) $data->slug, (string) $data->ownerName, (string) $data->ownerEmail);

        return $this->json($this->detail($account), 201);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(AccountDetailOutput::class)]
    public function show(string $id): JsonResponse
    {
        return $this->json($this->detail($this->load($id)));
    }

    /** Datos, and Límites y funciones. A field left out is left as it is. */
    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    #[ApiResponse(AccountDetailOutput::class)]
    public function update(string $id, Request $request, AccountSlugPolicy $slugs): JsonResponse
    {
        $account = $this->load($id);
        $data = $this->input->map($this->input->json($request), AccountUpdateInput::class);

        if (null !== $data->slug) {
            $account->setSlug($slugs->assertAvailable($data->slug, $account));
        }
        if (null !== $data->name) {
            $account->setName(trim($data->name));
        }
        if (null !== $data->country) {
            $account->setCountry($data->country);
        }
        if (null !== $data->currency) {
            $account->setCurrency($data->currency);
        }
        if (null !== $data->locale) {
            $account->setLocale($data->locale);
        }
        if (null !== $data->timezone) {
            $account->setTimezone($data->timezone);
        }
        $account->setLimits(
            $data->maxPublishedPages ?? $account->getMaxPublishedPages(),
            $data->maxAssistants ?? $account->getMaxAssistants(),
            $data->storageMb ?? $account->getStorageMb(),
            $data->maxFileMb ?? $account->getMaxFileMb(),
        );
        if (null !== $data->features) {
            $account->setFeatures(array_map(AccountFeature::from(...), $data->features));
        }
        $this->em->flush();

        return $this->json($this->detail($account));
    }

    /** Its people cannot sign in and its public pages are not served, until it is reactivated. Nothing is deleted. */
    #[Route('/{id}/suspend', name: 'suspend', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(AccountDetailOutput::class)]
    public function suspend(string $id): JsonResponse
    {
        $account = $this->load($id)->setActive(false);
        $this->em->flush();

        return $this->json($this->detail($account));
    }

    #[Route('/{id}/reactivate', name: 'reactivate', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(AccountDetailOutput::class)]
    public function reactivate(string $id): JsonResponse
    {
        $account = $this->load($id)->setActive(true);
        $this->em->flush();

        return $this->json($this->detail($account));
    }

    /** The owner and the assistants. ?q= searches name and email. Clients are the consultant's, not listed here. */
    #[Route('/{id}/users', name: 'users', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(TeamMemberOutput::class, page: true)]
    public function users(string $id, Request $request): JsonResponse
    {
        $page = $this->users->searchTeam($this->load($id), $request->query->getString('q'), $this->pagination($request));

        return $this->page($page, Presenter::teamMember(...));
    }

    #[Route('/{id}/users/{userId}/resend-invitation', name: 'user_resend', requirements: ['id' => Requirement::UUID, 'userId' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(TeamMemberOutput::class)]
    public function resendInvitation(string $id, string $userId): JsonResponse
    {
        return $this->json(Presenter::teamMember($this->invitations->resend($this->member($id, $userId))));
    }

    /** Disables an assistant. The owner is not disabled: suspend the consultant instead. */
    #[Route('/{id}/users/{userId}', name: 'user_disable', requirements: ['id' => Requirement::UUID, 'userId' => Requirement::UUID], methods: ['DELETE'])]
    #[ApiResponse(TeamMemberOutput::class)]
    public function disableUser(string $id, string $userId): JsonResponse
    {
        $user = $this->member($id, $userId);
        if ($user->hasRole(User::ROLE_OWNER)) {
            throw ApiException::conflict('owner_cannot_be_disabled', 'Suspend the consultant instead of disabling its owner.');
        }
        $user->setActive(false);
        $this->em->flush();

        return $this->json(Presenter::teamMember($user));
    }

    /** The super admin may enable an assistant beyond the consultant's limit: the limit binds the consultant. */
    #[Route('/{id}/users/{userId}/enable', name: 'user_enable', requirements: ['id' => Requirement::UUID, 'userId' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(TeamMemberOutput::class)]
    public function enableUser(string $id, string $userId): JsonResponse
    {
        $user = $this->member($id, $userId)->setActive(true);
        $this->em->flush();

        return $this->json(Presenter::teamMember($user));
    }

    private function load(string $id): Account
    {
        return $this->found($this->accounts->findOneById($id));
    }

    private function member(string $accountId, string $userId): User
    {
        return $this->found($this->users->findTeamMember($this->load($accountId), $userId));
    }

    private function detail(Account $account): AccountDetailOutput
    {
        return Presenter::accountDetail(
            $account,
            $this->users->findOwnersOf([$account])[(string) $account->getId()] ?? null,
            $this->users->countPeopleOf([$account])[(string) $account->getId()],
        );
    }
}
