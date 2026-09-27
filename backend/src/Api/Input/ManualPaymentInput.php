<?php

declare(strict_types=1);

namespace App\Api\Input;

use App\Enum\ManualPaymentMethod;
use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/enrollments/{id}/payments: money the owner received outside Wompi. */
final class ManualPaymentInput
{
    #[Assert\NotBlank(message: 'Choose how it was paid.')]
    #[Assert\Choice(callback: [ManualPaymentMethod::class, 'values'], message: 'Choose how it was paid.')]
    public ?string $method = null;

    #[Assert\Length(max: 500)]
    public ?string $note = null;

    /** Y-m-d, the consultant's day; today when absent. */
    #[Assert\Date(message: 'Enter a real date.')]
    public ?string $paidOn = null;
}
