<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Account;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base of every JSON controller: the signed-in user and their account, 404 for what is not found, pages and filters
 * the same way everywhere.
 */
abstract class ApiController extends AbstractController
{
    protected function appUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Expected an authenticated '.User::class.'.');
        }

        return $user;
    }

    /**
     * The consultant's account the request works in: the signed-in user's (the impersonated user's, for a super
     * admin acting as someone), loaded from the database, never from the token.
     */
    protected function account(): Account
    {
        return $this->appUser()->getAccount() ?? throw new \LogicException('The current user has no account.');
    }

    /**
     * @template T of object
     *
     * @param T|null $entity
     *
     * @return T
     */
    protected function found(?object $entity): object
    {
        return $entity ?? throw ApiException::notFound();
    }

    /**
     * ?page= and ?perPage= (25 by default, 100 at most).
     */
    protected function pagination(Request $request): Pagination
    {
        return Pagination::of($request->query->getInt('page', 1), $request->query->getInt('perPage', 25));
    }

    /**
     * @param callable(object): object $present an Output DTO factory
     */
    protected function page(Page $page, callable $present): JsonResponse
    {
        return $this->json([
            'items' => array_map($present, $page->items),
            'total' => $page->total,
            'page' => $page->pagination->page,
            'perPage' => $page->pagination->perPage,
        ]);
    }

    /**
     * An enum filter from the query string: empty is no filter, an unknown value a 400 (not "everything").
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    protected function enumQuery(string $value, string $enum, string $parameter): ?\BackedEnum
    {
        if ('' === $value) {
            return null;
        }

        return $enum::tryFrom($value) ?? throw ApiException::badRequest('invalid_filter', sprintf('Unknown value for "%s".', $parameter));
    }
}
