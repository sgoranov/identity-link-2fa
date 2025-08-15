<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserSecretRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserSecretRepository::class)]
class UserSecret
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\CustomIdGenerator(class: "doctrine.uuid_generator")]
    private string $id;

    #[ORM\Column(type: "text", unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 50)]
    private string $userId;

    #[ORM\Column(type: "text")]
    private string $secret;

    #[ORM\Column]
    private DateTime $created;

    #[ORM\Column]
    private bool $resetSecretOnNextAuth;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function setCreated(DateTime $created): void
    {
        $this->created = $created;
    }

    public function isResetSecretOnNextAuth(): bool
    {
        return $this->resetSecretOnNextAuth;
    }

    public function setResetSecretOnNextAuth(bool $resetSecretOnNextAuth): void
    {
        $this->resetSecretOnNextAuth = $resetSecretOnNextAuth;
    }
}
