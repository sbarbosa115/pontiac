<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\ApiValidationException;
use App\Api\InputMapper;
use App\Api\Output\ContactDetailOutput;
use App\Api\Output\ContactSummaryOutput;
use App\Api\Presenter;
use App\Entity\Contact;
use App\Entity\User;
use App\Enum\ContactStatus;
use App\Repository\BookingSessionRepository;
use App\Repository\ContactRepository;
use App\Repository\LeadCategoryRepository;
use App\Repository\LeadSubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Prospectos: the people who answered the consultant's pages. */
#[Route('/api/admin/contacts', name: 'api_admin_contact_')]
final class ContactController extends ApiController
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly LeadSubmissionRepository $submissions,
        private readonly BookingSessionRepository $sessions,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches name, email and phone; ?status=, ?category= (an id, or "none") and ?sourcePage= (an id) narrow it. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(ContactSummaryOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        $page = $this->contacts->search(
            $request->query->getString('q'),
            $this->enumQuery($request->query->getString('status'), ContactStatus::class, 'status'),
            $request->query->getString('category'),
            $request->query->getString('sourcePage'),
            $this->pagination($request),
        );

        return $this->page($page, Presenter::contactSummary(...));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(ContactDetailOutput::class)]
    public function show(string $id): JsonResponse
    {
        $contact = $this->load($id);

        return $this->json(Presenter::contactDetail($contact, $this->submissions->findForContact($contact), $this->sessions->findForContact($contact)));
    }

    /** {categoryId}: one of the consultant's categories, or null for none. */
    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    #[ApiResponse(ContactDetailOutput::class)]
    public function update(string $id, Request $request, InputMapper $input, LeadCategoryRepository $categories): JsonResponse
    {
        $contact = $this->load($id);
        $data = $input->json($request);
        if (\array_key_exists('categoryId', $data)) {
            $categoryId = $data['categoryId'];
            $category = \is_string($categoryId) ? $categories->findOneById($categoryId) : null;
            if (null !== $categoryId && null === $category) {
                throw ApiValidationException::single('categoryId', 'Choose one of your categories.');
            }
            $contact->setCategory($category);
        }
        $this->em->flush();

        return $this->json(Presenter::contactDetail($contact, $this->submissions->findForContact($contact), $this->sessions->findForContact($contact)));
    }

    /**
     * Ley 1581, on the person's request: their name, email, phone and answers are erased for good. Only the owner, who
     * answers for the data, can do it.
     */
    #[Route('/{id}/anonymize', name: 'anonymize', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(ContactDetailOutput::class)]
    public function anonymize(string $id): JsonResponse
    {
        $contact = $this->load($id);
        if ($contact->isAnonymized()) {
            throw ApiException::conflict('already_anonymized', 'This contact\'s data was already erased.');
        }
        $contact->anonymize();
        $submissions = $this->submissions->findForContact($contact);
        foreach ($submissions as $submission) {
            $submission->anonymize();
        }
        $this->em->flush();

        return $this->json(Presenter::contactDetail($contact, $submissions, $this->sessions->findForContact($contact)));
    }

    private function load(string $id): Contact
    {
        return $this->found($this->contacts->findOneById($id));
    }
}
