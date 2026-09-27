<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\EmailTemplateInput;
use App\Api\InputMapper;
use App\Api\Output\EmailTemplateOutput;
use App\Entity\EmailTemplate;
use App\Enum\AccountFeature;
use App\Repository\EmailTemplateRepository;
use App\Security\RequiresFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** Ajustes › Correos: the emails flows send when someone enters a stage. */
#[Route('/api/admin/email-templates', name: 'api_admin_email_template_')]
#[RequiresFeature(AccountFeature::Flows)]
final class EmailTemplateController extends ApiController
{
    public function __construct(
        private readonly EmailTemplateRepository $templates,
        private readonly InputMapper $input,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches the name and subject; ?includeInactive=1 shows disabled ones too. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(EmailTemplateOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        return $this->page($this->templates->search($request->query->getString('q'), $request->query->getBoolean('includeInactive'), $this->pagination($request)), self::present(...));
    }

    #[Route('/all', name: 'all', methods: ['GET'])]
    #[ApiResponse(EmailTemplateOutput::class, list: true, key: 'items')]
    public function all(): JsonResponse
    {
        return $this->json(['items' => array_map(self::present(...), $this->templates->findAllForPickers())]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[ApiResponse(EmailTemplateOutput::class, status: 201)]
    public function create(Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), EmailTemplateInput::class);
        $template = new EmailTemplate($this->account(), trim((string) $data->name), trim((string) $data->subject), trim((string) $data->body));
        $this->em->persist($template);
        $this->em->flush();

        return $this->json(self::present($template), 201);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[ApiResponse(EmailTemplateOutput::class)]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), EmailTemplateInput::class);
        $template = $this->load($id)->change(trim((string) $data->name), trim((string) $data->subject), trim((string) $data->body));
        $this->em->flush();

        return $this->json(self::present($template));
    }

    /** Disabled: stages that use it send nothing until it is enabled again. */
    #[Route('/{id}', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[ApiResponse(EmailTemplateOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $template = $this->load($id)->setActive(false);
        $this->em->flush();

        return $this->json(self::present($template));
    }

    #[Route('/{id}/enable', name: 'enable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(EmailTemplateOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $template = $this->load($id)->setActive(true);
        $this->em->flush();

        return $this->json(self::present($template));
    }

    private function load(string $id): EmailTemplate
    {
        return $this->found($this->templates->findOneById($id));
    }

    private static function present(EmailTemplate $t): EmailTemplateOutput
    {
        return new EmailTemplateOutput(id: (string) $t->getId(), name: $t->getName(), subject: $t->getSubject(), body: $t->getBody(), active: $t->isActive());
    }
}
