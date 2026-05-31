<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendTelegramConfirmationMessage
{
    public function __construct(
        public int $userId,
        public int $confirmationCodeId,
    ) {}
}
