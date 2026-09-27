<?php

declare(strict_types=1);

namespace App\Controller\Platform;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\TestEmailInput;
use App\Api\InputMapper;
use App\Api\Output\OutgoingEmailOutput;
use App\Api\Presenter;
use App\Enum\EmailStatus;
use App\Mail\EmailTag;
use App\Repository\OutgoingEmailRepository;
use App\Repository\PlatformSettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Plataforma › Correos: every email Pontiac tried to send, and a test email to check the mail server. */
#[Route('/api/platform/emails', name: 'api_platform_email_')]
final class EmailController extends ApiController
{
    /** ?q= searches recipient, subject and consultant; ?status= "sent" or "failed"; ?account= a consultant's id. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(OutgoingEmailOutput::class, page: true)]
    public function list(Request $request, OutgoingEmailRepository $emails): JsonResponse
    {
        $page = $emails->search(
            $request->query->getString('q'),
            $this->enumQuery($request->query->getString('status'), EmailStatus::class, 'status'),
            $request->query->getString('account'),
            $this->pagination($request),
        );

        return $this->page($page, Presenter::outgoingEmail(...));
    }

    /** Sent at once, not queued: the super admin waits to see whether the mail server takes it (502 email_failed). */
    #[Route('/test', name: 'test', methods: ['POST'])]
    public function test(
        Request $request,
        InputMapper $input,
        PlatformSettingsRepository $settings,
        #[Autowire(service: 'mailer.transports')]
        TransportInterface $transport,
        #[Autowire('%env(MAILER_FROM)%')]
        string $from,
        #[Autowire(service: 'limiter.platform_test_email')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        $data = $input->map($input->json($request), TestEmailInput::class);
        if (!$limiter->create((string) $this->appUser()->getId())->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('too_many_attempts', 'Too many test emails. Try again later.');
        }

        $current = $settings->current();
        $email = (new Email())
            ->from(new Address($from, $current->getSenderName()))
            ->replyTo($current->getSupportEmail())
            ->to((string) $data->to)
            ->subject(sprintf('%s: correo de prueba', $current->getPlatformName()))
            ->text("Este es un correo de prueba de la configuración de correo de {$current->getPlatformName()}. Si lo recibes, el servidor de correo funciona.");
        $transport->send(EmailTag::apply($email, EmailTag::TEST));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
