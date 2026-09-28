<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WompiSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A consultant's Wompi merchant (Ajustes › Pagos Wompi): each consultant is paid into their own account. The public
 * key is public; the other three are stored encrypted (App\Security\SecretBox) and never leave the server. Test or
 * production follows from the keys ("pub_test_…", "pub_prod_…").
 */
#[ORM\Entity(repositoryClass: WompiSettingsRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_wompi_settings_account', columns: ['account_id'])]
class WompiSettings implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    public const MODE_TEST = 'test';
    public const MODE_PRODUCTION = 'production';

    #[ORM\Column(length: 120)]
    private string $publicKey = '';

    // Encrypted; empty until given.
    #[ORM\Column(type: Types::TEXT)]
    private string $privateKey = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $eventsSecret = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $integritySecret = '';

    /** @var array<string, string> the last four characters of each secret, so the screen can say which one is stored */
    #[ORM\Column(type: Types::JSON)]
    private array $endings = [];

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Account $account)
    {
        $this->id = Uuid::v7();
        $this->setAccount($account);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** The mode a key says it is for, or null when it is not a Wompi key. */
    public static function modeOf(string $key): ?string
    {
        return match (true) {
            1 === preg_match('/^(pub|prv)_test_|^test_(events|integrity)_/', $key) => self::MODE_TEST,
            1 === preg_match('/^(pub|prv)_prod_|^prod_(events|integrity)_/', $key) => self::MODE_PRODUCTION,
            default => null,
        };
    }

    public function setPublicKey(string $publicKey): static
    {
        $this->publicKey = $publicKey;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @param 'privateKey'|'eventsSecret'|'integritySecret' $name
     */
    public function setSecret(string $name, string $sealed, string $plain): static
    {
        $this->{$name} = $sealed;
        $this->endings[$name] = substr($plain, -4);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @param 'privateKey'|'eventsSecret'|'integritySecret' $name
     */
    public function getSealedSecret(string $name): string
    {
        return $this->{$name};
    }

    /**
     * @param 'privateKey'|'eventsSecret'|'integritySecret' $name
     */
    public function getEnding(string $name): ?string
    {
        return '' === $this->{$name} ? null : ($this->endings[$name] ?? null);
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getMode(): ?string
    {
        return '' === $this->publicKey ? null : self::modeOf($this->publicKey);
    }

    /** Enough to take payments: the public key (checkout), the integrity secret (its signature) and the events secret. */
    public function isConfigured(): bool
    {
        return '' !== $this->publicKey && '' !== $this->integritySecret && '' !== $this->eventsSecret;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
