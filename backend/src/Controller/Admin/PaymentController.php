<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Output\PaymentOutput;
use App\Api\Presenter;
use App\Enum\AccountFeature;
use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use App\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Pagos: every payment, through Wompi or recorded by hand. */
#[Route('/api/admin/payments', name: 'api_admin_payment_')]
#[RequiresFeature(AccountFeature::Payments)]
final class PaymentController extends ApiController
{
    /** ?q= searches the person's name and email and the reference; ?status=; ?from= and ?to= (Y-m-d, local days, inclusive). */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(PaymentOutput::class, page: true)]
    public function list(Request $request, PaymentRepository $payments): JsonResponse
    {
        $page = $payments->search(
            $request->query->getString('q'),
            $this->enumQuery($request->query->getString('status'), PaymentStatus::class, 'status'),
            $this->localDay($request->query->getString('from'), 'from'),
            $this->localDay($request->query->getString('to'), 'to')?->modify('+1 day'),
            $this->pagination($request),
        );

        return $this->page($page, Presenter::payment(...));
    }

    private function localDay(string $value, string $parameter): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone($this->account()->getTimezone()));
        if (false === $day || $day->format('Y-m-d') !== $value) {
            throw ApiException::badRequest('invalid_filter', sprintf('"%s" must be a date (Y-m-d).', $parameter));
        }

        return $day;
    }
}
