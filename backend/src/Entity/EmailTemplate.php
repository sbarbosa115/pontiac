<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * An email the consultant writes once and flows send (Ajustes › Correos). Subject and body take variables:
 * {nombre}, {asesor}, {fecha_sesion}, {enlace_reserva}, {enlace_pago}, {enlace_portal}.
 */
#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ORM\Index(name: 'idx_email_template_account_name', columns: ['account_id', 'name'])]
class EmailTemplate implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    public const VARIABLES = ['nombre', 'asesor', 'fecha_sesion', 'enlace_reserva', 'enlace_pago', 'enlace_portal'];

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 200)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(Account $account, string $name, string $subject, string $body)
    {
        $this->id = Uuid::v7();
        $this->setAccount($account);
        $this->name = $name;
        $this->subject = $subject;
        $this->body = $body;
    }

    public function change(string $name, string $subject, string $body): static
    {
        $this->name = $name;
        $this->subject = $subject;
        $this->body = $body;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
