<?php

declare(strict_types=1);

namespace App\Flow;

use App\Entity\Account;
use App\Entity\Contact;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The "no quiero recibir más correos" link of flow emails: the contact's id signed with the app secret, so nobody can
 * stop someone else's emails by guessing.
 */
final class OptOutLink
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    public function url(Account $account, Contact $contact): string
    {
        return rtrim($this->appUrl, '/').'/'.$account->getSlug().'/correos/baja/'.$contact->getId().'/'.$this->signature((string) $contact->getId());
    }

    public function isValid(string $contactId, string $signature): bool
    {
        return hash_equals($this->signature($contactId), $signature);
    }

    private function signature(string $contactId): string
    {
        return substr(hash_hmac('sha256', 'flow-opt-out:'.$contactId, $this->secret), 0, 32);
    }
}
