<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendVerificationCodeMessage
{
    public function __construct(
        public int $userId,
        public string $code,
    ) {}
}
