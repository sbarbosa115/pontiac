<?php

declare(strict_types=1);

namespace App\Enum;

/** What the consultant chose once a plan was used up: another plan, or the end of the consultancy. */
enum EnrollmentOutcome: string
{
    case Renewed = 'renewed';
    case Finished = 'finished';
}
