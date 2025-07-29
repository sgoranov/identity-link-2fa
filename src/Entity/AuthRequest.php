<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuthRequestRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthRequestRepository::class)]
#[OA\Schema(
    schema: 'AuthRequest',
    title: 'AuthRequest',
    description: 'AuthRequest entity schema used for both input and output',
    required: ['identifier', 'created', 'expired'],
    properties: [
        new OA\Property(
            property: 'id',
            description: 'UUID of the auth request',
            type: 'string',
            format: 'uuid',
            readOnly: true,
            example: 'a1b2c3d4-e5f6-7890-1234-abcdef123456'
        ),
        new OA\Property(
            property: 'identifier',
            description: 'Unique identifier for the auth request (e.g., device ID or username)',
            type: 'string',
            maxLength: 50,
            example: 'user_123'
        ),
        new OA\Property(
            property: 'created',
            description: 'Date and time when the auth request was created',
            type: 'string',
            format: 'date-time',
            example: '2025-07-30T12:00:00+00:00'
        ),
        new OA\Property(
            property: 'expired',
            description: 'Date and time when the auth request expires',
            type: 'string',
            format: 'date-time',
            example: '2025-07-30T12:05:00+00:00'
        ),
        new OA\Property(
            property: 'authenticated',
            description: 'Date and time when the auth request was successfully authenticated, if any',
            type: 'string',
            format: 'date-time',
            example: '2025-07-30T12:02:00+00:00',
            nullable: true
        )
    ],
    type: 'object'
)]
class AuthRequest
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\CustomIdGenerator(class: "doctrine.uuid_generator")]
    private ?string $id = null;

    #[Groups(['create'])]
    #[ORM\Column(type: "text")]
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(min: 1, max: 50, groups: ['create'])]
    private string $identifier;

    #[ORM\Column]
    private DateTime $created;

    #[ORM\Column]
    private DateTime $expired;

    #[ORM\Column(nullable: true)]
    private ?DateTime $authenticated = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function setCreated(DateTime $created): void
    {
        $this->created = $created;
    }

    public function getExpired(): DateTime
    {
        return $this->expired;
    }

    public function setExpired(DateTime $expired): void
    {
        $this->expired = $expired;
    }

    public function getAuthenticated(): ?DateTime
    {
        return $this->authenticated;
    }

    public function setAuthenticated(?DateTime $authenticated): void
    {
        $this->authenticated = $authenticated;
    }
}
