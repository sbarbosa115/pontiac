<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Doctrine\AccountContext;
use App\Entity\Account;
use App\Entity\LandingPage;
use App\Entity\LeadCategory;
use App\Entity\User;
use App\Enum\PageTemplate;
use App\Page\TemplateCatalog;
use App\Page\TimeToken;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Builds the world directly (outside any account scope, so accounts are set explicitly), then exercises the API over
 * HTTP. Assertions go through the API, not the entity manager, because the test itself runs outside any account.
 * Each test runs in a transaction that is rolled back (DAMA), on the app_test database.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'correct-horse-battery';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * The test's own reads and writes see every account: the account a request entered stays entered after it, and
     * the test builds and inspects several accounts' data. Requests enter their own scope again.
     */
    protected function asPlatform(): void
    {
        static::getContainer()->get(AccountContext::class)->enterPlatformScope();
    }

    protected function save(object ...$entities): void
    {
        $this->asPlatform();
        foreach ($entities as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();
    }

    protected function createAccount(string $name = 'Finanzas Claras', ?string $slug = null): Account
    {
        $account = new Account($name, $slug ?? strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-')));
        $this->save($account);

        return $account;
    }

    protected function createOwner(Account $account, string $email = 'asesor@demo.test', string $fullName = 'Andrés Asesor'): User
    {
        return $this->withPassword(User::createOwner($account, $email, $fullName));
    }

    protected function createAssistant(Account $account, string $email = 'asistente@demo.test', string $fullName = 'Sofía Asistente'): User
    {
        return $this->withPassword(User::createAssistant($account, $email, $fullName));
    }

    protected function createClientLogin(Account $account, string $email = 'cliente@demo.test', string $fullName = 'Carlos Cliente'): User
    {
        return $this->withPassword(User::createClient($account, $email, $fullName));
    }

    protected function createSuperAdmin(string $email = 'ops@pontiac.test'): User
    {
        return $this->withPassword(User::createSuperAdmin($email, 'Paula Plataforma'));
    }

    /**
     * A page from a template's defaults, published unless said otherwise. $change edits its content first.
     *
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $change
     */
    protected function createPage(Account $account, string $slug = 'diagnostico', PageTemplate $template = PageTemplate::FreeDiagnostic, bool $published = true, bool $home = false, ?callable $change = null): LandingPage
    {
        $content = TemplateCatalog::newContent($template, 'Página '.$slug);
        if (null !== $change) {
            $content = $change($content);
        }
        $page = (new LandingPage($account, 'Página '.$slug, $slug, $template, $content))->setHome($home);
        if ($published) {
            $page->publish();
        }
        $this->save($page);

        return $page;
    }

    protected function createCategory(Account $account, string $name = 'Deudas', string $color = 'rose'): LeadCategory
    {
        $category = new LeadCategory($account, $name, $color);
        $this->save($category);

        return $category;
    }

    /**
     * What a person sends from a page's form, the form drawn long enough ago to count as a person.
     *
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    protected function formData(string $email = 'laura@demo.test', array $extra = []): array
    {
        return $extra + [
            'name' => 'Laura Gómez',
            'email' => $email,
            'phone' => '300 123 4567',
            'consent' => '1',
            'website' => '',
            '_t' => static::getContainer()->get(TimeToken::class)->issue(time() - 30),
        ];
    }

    /**
     * Authenticates the next requests as $user and clears the identity map, so requests load everything through
     * the (scoped) database queries.
     */
    protected function actAs(User $user): void
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
        $this->em()->clear();
    }

    protected function signOut(): void
    {
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->client->setServerParameter('HTTP_X_SWITCH_USER', '');
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    protected function api(string $method, string $uri, ?array $body = null): array
    {
        if (null === $body) {
            $this->client->request($method, $uri);
        } else {
            $this->client->jsonRequest($method, $uri, $body);
        }

        return $this->decode();
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>
     */
    protected function upload(string $uri, array $fields, array $files): array
    {
        $this->client->request('POST', $uri, $fields, $files);

        return $this->decode();
    }

    /** A real image of that size, drawn with GD, as a browser would upload it. */
    protected function imageFile(int $width = 1200, int $height = 800, string $name = 'foto.jpg'): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'img');
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 30, 58, 95));
        imagejpeg($image, $path, 85);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    protected function pngFile(string $name = 'foto.png'): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    /**
     * Does what "messenger:consume async" does on the server: handles every queued message that is due, including
     * the ones the handlers queue themselves, until nothing due is left. Call it right after the request that queued
     * the messages: the next request reboots the kernel, and the in-memory queue with it.
     *
     * @return int how many messages were handled
     */
    protected function runWorker(bool $skipDelays = false, int $limit = 1000): int
    {
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $bus = static::getContainer()->get(MessageBusInterface::class);
        // The transport keeps when each delayed message becomes available; forgetting it is time passing.
        $due = static function () use ($transport, $skipDelays): array {
            if ($skipDelays) {
                (fn () => $this->availableAt = [])->call($transport);
            }

            return iterator_to_array($transport->get(), false);
        };

        $handled = 0;
        while ($handled < $limit && [] !== $envelopes = $due()) {
            foreach ($envelopes as $envelope) {
                $transport->ack($envelope);
                $bus->dispatch($envelope->with(new ReceivedStamp('async')));
                ++$handled;
            }
        }

        return $handled;
    }

    protected function responseStatus(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $content = $this->client->getResponse()->getContent();

        return \is_string($content) && '' !== $content && str_contains((string) $this->client->getResponse()->headers->get('Content-Type'), 'json')
            ? json_decode($content, true, flags: \JSON_THROW_ON_ERROR)
            : [];
    }

    protected function withPassword(User $user): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $this->save($user);

        return $user;
    }
}
