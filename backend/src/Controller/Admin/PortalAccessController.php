<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\ContactDetails;
use App\Api\Output\ContactDetailOutput;
use App\Entity\Contact;
use App\Portal\PortalAccess;
use App\Repository\ContactRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** A contact's portal access, from their page: invite (or invite again), take the access away, give it back. */
#[Route('/api/admin/contacts/{id}/portal', name: 'api_admin_portal_access_', requirements: ['id' => Requirement::UUID])]
final class PortalAccessController extends ApiController
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly PortalAccess $portal,
        private readonly ContactDetails $details,
    ) {
    }

    #[Route('/invitation', name: 'invite', methods: ['POST'])]
    #[ApiResponse(ContactDetailOutput::class)]
    public function invite(string $id): JsonResponse
    {
        $contact = $this->load($id);
        $this->portal->invite($this->account(), $contact);

        return $this->json($this->details->of($this->account(), $contact));
    }

    #[Route('/disable', name: 'disable', methods: ['POST'])]
    #[ApiResponse(ContactDetailOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $contact = $this->load($id);
        $this->portal->setEnabled($contact, false);

        return $this->json($this->details->of($this->account(), $contact));
    }

    #[Route('/enable', name: 'enable', methods: ['POST'])]
    #[ApiResponse(ContactDetailOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $contact = $this->load($id);
        $this->portal->setEnabled($contact, true);

        return $this->json($this->details->of($this->account(), $contact));
    }

    private function load(string $id): Contact
    {
        return $this->found($this->contacts->findOneById($id));
    }
}
