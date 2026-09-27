<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * UUIDv7 primary keys: time-ordered (index friendly) and not enumerable across accounts.
 */
trait HasUuid
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    public function getId(): Uuid
    {
        return $this->id;
    }
}
