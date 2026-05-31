<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsSourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NewsSourceRepository::class)]
#[ORM\Table(name: 'news_sources')]
class NewsSource
{
    public const TYPE_RSS = 'rss';
    public const TYPE_API = 'api';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 400)]
    #[Assert\NotBlank]
    #[Assert\Url]
    private ?string $url = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_RSS;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $defaultCategory = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastFetchedAt = null;

    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'source')]
    private Collection $articles;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDefaultCategory(): ?Category
    {
        return $this->defaultCategory;
    }

    public function setDefaultCategory(?Category $defaultCategory): static
    {
        $this->defaultCategory = $defaultCategory;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastFetchedAt(): ?\DateTimeImmutable
    {
        return $this->lastFetchedAt;
    }

    public function setLastFetchedAt(?\DateTimeImmutable $lastFetchedAt): static
    {
        $this->lastFetchedAt = $lastFetchedAt;
        return $this;
    }

    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
