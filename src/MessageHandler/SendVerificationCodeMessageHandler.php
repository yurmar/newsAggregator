<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendVerificationCodeMessage;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendVerificationCodeMessageHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendVerificationCodeMessage $message): void
    {
        $user = $this->userRepository->find($message->userId);
        if (!$user) {
            return;
        }

        // TODO: отправить реальное письмо через Mailer
        $this->logger->info('Verification code sent', [
            'user'  => $user->getEmail(),
            'code'  => $message->code,
        ]);
    }
}
