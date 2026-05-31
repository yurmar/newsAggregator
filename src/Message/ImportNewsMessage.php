<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ImportNewsMessage
{
    public function __construct(
        public int $sourceId,
    ) {}
}
