<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VerificationCodeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VerificationCodeRepository::class)]
#[ORM\Table(name: 'verification_codes')]
class VerificationCode
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const MAX_ATTEMPTS = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 6)]
    private string $code;

    #[ORM\Column(length: 10)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private int $attemptCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $confirmedAt = null;

    public function __construct(User $user, string $code)
    {
        $this->user = $user;
        $this->code = $code;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }

    public function getCode(): string { return $this->code; }

    public function getStatus(): string { return $this->status; }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getAttemptCount(): int { return $this->attemptCount; }

    public function incrementAttempts(): static
    {
        $this->attemptCount++;
        return $this;
    }

    public function isMaxAttemptsReached(): bool
    {
        return $this->attemptCount >= self::MAX_ATTEMPTS;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }

    public function setSentAt(\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function canResend(): bool
    {
        if ($this->sentAt === null) {
            return true;
        }
        return $this->sentAt->getTimestamp() + 60 <= time();
    }

    public function getSecondsUntilResend(): int
    {
        if ($this->sentAt === null) {
            return 0;
        }
        return max(0, ($this->sentAt->getTimestamp() + 60) - time());
    }

    public function resetForResend(string $newCode): static
    {
        $this->code = $newCode;
        $this->sentAt = new \DateTimeImmutable();
        $this->attemptCount = 0;
        $this->status = self::STATUS_PENDING;
        return $this;
    }

    public function getConfirmedAt(): ?\DateTime { return $this->confirmedAt; }

    public function setConfirmedAt(\DateTime $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;
        return $this;
    }
}
