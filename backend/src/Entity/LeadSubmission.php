<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LeadSubmissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** One form sent from one page: what the person answered, as the form was then. */
#[ORM\Entity(repositoryClass: LeadSubmissionRepository::class)]
#[ORM\Index(name: 'idx_submission_account_page_at', columns: ['account_id', 'page_id', 'submitted_at'])]
#[ORM\Index(name: 'idx_submission_account_contact_at', columns: ['account_id', 'contact_id', 'submitted_at'])]
class LeadSubmission implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    #[ORM\ManyToOne(targetEntity: LandingPage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private LandingPage $page;

    /** @var list<array{key: string, label: string, value: string}> the extra fields, labelled as they were */
    #[ORM\Column(type: Types::JSON)]
    private array $answers;

    /** @var array<string, string> utm_source, utm_medium, utm_campaign… */
    #[ORM\Column(type: Types::JSON)]
    private array $utm;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $referrer;

    #[ORM\Column]
    private \DateTimeImmutable $submittedAt;

    /**
     * @param list<array{key: string, label: string, value: string}> $answers
     * @param array<string, string>                                   $utm
     */
    public function __construct(Contact $contact, LandingPage $page, array $answers, array $utm, ?string $referrer)
    {
        $this->id = Uuid::v7();
        $this->setAccount($contact->getAccount() ?? throw new \LogicException('A contact belongs to an account.'));
        $this->contact = $contact;
        $this->page = $page;
        $this->answers = $answers;
        $this->utm = $utm;
        $this->referrer = null === $referrer ? null : mb_substr($referrer, 0, 500);
        $this->submittedAt = new \DateTimeImmutable();
    }

    /** Ley 1581: the answers go with the person's data. */
    public function anonymize(): static
    {
        $this->answers = [];
        $this->referrer = null;

        return $this;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getPage(): LandingPage
    {
        return $this->page;
    }

    /**
     * @return list<array{key: string, label: string, value: string}>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * @return array<string, string>
     */
    public function getUtm(): array
    {
        return $this->utm;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }
}
