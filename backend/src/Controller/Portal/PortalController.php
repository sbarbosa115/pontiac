<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Entity\Contact;

/**
 * The portal's controllers: everything is the signed-in client's own, read from their login and never from the
 * request. Another person's ids answer 404.
 */
abstract class PortalController extends ApiController
{
    protected function contact(): Contact
    {
        return $this->appUser()->getContact() ?? throw ApiException::conflict('portal_not_linked', 'This login is not linked to a person yet: ask your consultant to invite you again.');
    }

    /** 404 unless the thing is theirs. */
    protected function own(Contact $of): void
    {
        if (!$of->getId()->equals($this->contact()->getId())) {
            throw ApiException::notFound();
        }
    }
}
