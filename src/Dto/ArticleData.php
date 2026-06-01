<?php

declare(strict_types=1);

namespace App\Dto;

final class ArticleData
{
    public function __construct(
        public readonly string $title,
        public readonly string $externalUrl,
        public readonly ?string $summary = null,
        public readonly ?string $content = null,
        public readonly ?string $imageUrl = null,
        public readonly ?\DateTimeImmutable $publishedAt = null,
    ) {}
}
