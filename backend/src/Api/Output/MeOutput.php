<?php

declare(strict_types=1);

namespace App\Api\Output;

/** GET /api/me: who is signed in (or acted as), and what the UI needs to show them. */
final readonly class MeOutput
{
    public function __construct(
        public string $id,
        public string $email,
        public string $fullName,
        /** @var list<string> */
        public array $roles,
        /** The consultant's account; null for a super admin. */
        public ?MeAccountOutput $account,
        /** The person at the screen's own theme: the super admin's while they act as someone. */
        public string $uiTheme,
        /** null unless a super admin is acting as this user */
        public ?ImpersonatorOutput $impersonator,
    ) {
    }
}
