<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Account;
use Symfony\Component\Mime\Email;

/**
 * What an email is and which consultant it was sent for, for the email log (Correos). Carried as two headers from the
 * mailer to App\Mail\EmailLog — through the queue too — which takes them off before the email leaves the server.
 */
final class EmailTag
{
    public const KIND_HEADER = 'X-Pontiac-Kind';
    public const ACCOUNT_HEADER = 'X-Pontiac-Account';

    public const INVITATION = 'invitation';
    public const TEST = 'test';
    public const NEW_LEAD = 'new_lead';
    public const RESOURCE = 'resource';

    public static function apply(Email $email, string $kind, ?Account $account = null): Email
    {
        $headers = $email->getHeaders();
        $headers->addTextHeader(self::KIND_HEADER, $kind);
        if (null !== $account) {
            $headers->addTextHeader(self::ACCOUNT_HEADER, $account->getId()->toRfc4122());
        }

        return $email;
    }
}
