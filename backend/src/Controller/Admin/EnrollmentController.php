<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\ContactDetails;
use App\Api\Input\AssignPlanInput;
use App\Api\Input\ManualPaymentInput;
use App\Api\InputMapper;
use App\Api\Output\ContactDetailOutput;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Payment\Enrollments;
use App\Payment\PaymentApplier;
use App\Repository\ContactRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\PlanRepository;
use App\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A contact's plans (Planes y pagos): assign one, send its payment link, cancel it, record a payment received, and
 * renew or finish once it is used up. Every answer is the contact's page, as it is now.
 */
#[Route('/api/admin', name: 'api_admin_enrollment_')]
final class EnrollmentController extends ApiController
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments,
        private readonly Enrollments $service,
        private readonly ContactDetails $details,
        private readonly InputMapper $input,
    ) {
    }

    /** "Asignar plan": a free one starts at once; a paid one waits for its payment and emails the link. */
    #[Route('/contacts/{id}/enrollments', name: 'assign', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(ContactDetailOutput::class, status: 201)]
    public function assign(string $id, Request $request, ContactRepository $contacts, PlanRepository $plans): JsonResponse
    {
        $contact = $this->found($contacts->findOneById($id));
        $data = $this->input->map($this->input->json($request), AssignPlanInput::class);
        $this->service->assign($this->account(), $contact, $this->found($plans->findOneById((string) $data->planId)));

        return $this->json($this->details->of($this->account(), $contact), 201);
    }

    #[Route('/enrollments/{id}/send-link', name: 'send_link', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[RequiresFeature(AccountFeature::Payments)]
    #[ApiResponse(ContactDetailOutput::class)]
    public function sendLink(string $id): JsonResponse
    {
        $enrollment = $this->load($id);
        $this->service->sendLink($this->account(), $enrollment);

        return $this->detail($enrollment);
    }

    /** Only a plan still waiting for its payment. */
    #[Route('/enrollments/{id}/cancel', name: 'cancel', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(ContactDetailOutput::class)]
    public function cancel(string $id): JsonResponse
    {
        return $this->detail($this->service->cancel($this->load($id)));
    }

    /** Money received outside Wompi (cash, a transfer): the plan starts and the person becomes a client. */
    #[Route('/enrollments/{id}/payments', name: 'manual_payment', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[RequiresFeature(AccountFeature::Payments)]
    #[ApiResponse(ContactDetailOutput::class, status: 201)]
    public function manualPayment(string $id, Request $request, PaymentApplier $applier): JsonResponse
    {
        $enrollment = $this->load($id);
        $data = $this->input->map($this->input->json($request), ManualPaymentInput::class);
        $zone = new \DateTimeZone($this->account()->getTimezone());
        $paidAt = null === $data->paidOn || '' === $data->paidOn
            ? new \DateTimeImmutable()
            : (new \DateTimeImmutable($data->paidOn.' 12:00', $zone));
        $applier->recordManual($this->account(), $enrollment, (string) $data->method, '' === trim((string) $data->note) ? null : trim((string) $data->note), $this->appUser(), $paidAt);

        return $this->json($this->details->of($this->account(), $enrollment->getContact()), 201);
    }

    /** "Renovar": the used-up plan is closed and another one starts (a paid one sends its payment link). */
    #[Route('/enrollments/{id}/renew', name: 'renew', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(ContactDetailOutput::class, status: 201)]
    public function renew(string $id, Request $request, PlanRepository $plans): JsonResponse
    {
        $enrollment = $this->load($id);
        $data = $this->input->map($this->input->json($request), AssignPlanInput::class);
        $this->service->renew($this->account(), $enrollment, $this->found($plans->findOneById((string) $data->planId)));

        return $this->json($this->details->of($this->account(), $enrollment->getContact()), 201);
    }

    /** "Finalizar asesoría": the used-up plan is closed and the person is finished (paying again brings them back). */
    #[Route('/enrollments/{id}/finish', name: 'finish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(ContactDetailOutput::class)]
    public function finish(string $id): JsonResponse
    {
        return $this->detail($this->service->finish($this->load($id)));
    }

    private function load(string $id): Enrollment
    {
        return $this->found($this->enrollments->findOneById($id));
    }

    private function detail(Enrollment $enrollment): JsonResponse
    {
        return $this->json($this->details->of($this->account(), $enrollment->getContact()));
    }
}
