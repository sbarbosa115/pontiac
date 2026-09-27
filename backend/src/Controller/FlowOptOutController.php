<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Account;
use App\Flow\OptOutLink;
use App\Page\PublicResolver;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * "No quiero recibir más correos", from a flow email: a page that asks, then stops flow emails for the person. A
 * confirmation (a POST) rather than the link itself, so mail scanners that open links do not stop anything.
 */
final class FlowOptOutController extends AbstractController
{
    #[Route('/{slug}/correos/baja/{contactId}/{signature}', name: 'public_flow_opt_out', requirements: ['slug' => Account::SLUG_PATTERN, 'contactId' => Requirement::UUID, 'signature' => '[0-9a-f]{32}'], methods: ['GET', 'POST'], priority: 5)]
    public function __invoke(string $slug, string $contactId, string $signature, Request $request, PublicResolver $resolver, OptOutLink $links, ContactRepository $contacts, EntityManagerInterface $em): Response
    {
        $account = $resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $contact = $links->isValid($contactId, $signature) ? $contacts->findOneById($contactId) : null;
        if (null === $contact) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            $contact->stopFlowEmails(new \DateTimeImmutable());
            $em->flush();
        }

        return $this->render('public/flow_opt_out.html.twig', ['account' => $account, 'stopped' => null !== $contact->getFlowEmailsStoppedAt()]);
    }
}
