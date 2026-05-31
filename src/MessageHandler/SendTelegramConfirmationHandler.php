<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\VerificationCode;
use App\Message\SendTelegramConfirmationMessage;
use App\Repository\UserRepository;
use App\Repository\VerificationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class SendTelegramConfirmationHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly VerificationCodeRepository $codeRepository,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private readonly string $botToken,
        #[Autowire('%env(TELEGRAM_CHANNEL_ID)%')]
        private readonly string $chatId,
    ) {}

    public function __invoke(SendTelegramConfirmationMessage $message): void
    {
        $user = $this->userRepository->find($message->userId);
        $code = $this->codeRepository->find($message->confirmationCodeId);

        if (!$user || !$code) {
            $this->logger->error('SendTelegramConfirmation: user or code not found', [
                'userId'             => $message->userId,
                'confirmationCodeId' => $message->confirmationCodeId,
            ]);
            return;
        }

        $text = sprintf(
            "Привет, %s!\n\nВаш код подтверждения: *%s*\n\nКод действителен 10 минут.",
            $user->getName(),
            $code->getCode(),
        );

        try {
            $response = $this->httpClient->request('POST', sprintf(
                'https://api.telegram.org/bot%s/sendMessage',
                $this->botToken,
            ), [
                'json' => [
                    'chat_id'    => $this->chatId,
                    'text'       => $text,
                    'parse_mode' => 'Markdown',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(sprintf(
                    'Telegram API error %d: %s',
                    $response->getStatusCode(),
                    $response->getContent(false),
                ));
            }

            $code->setStatus(VerificationCode::STATUS_SENT);
            $code->setSentAt(new \DateTimeImmutable());
            $this->em->flush();

        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Telegram confirmation code', [
                'userId'             => $message->userId,
                'confirmationCodeId' => $message->confirmationCodeId,
                'error'              => $e->getMessage(),
            ]);

            $code->setStatus(VerificationCode::STATUS_FAILED);
            $this->em->flush();

            throw $e;
        }
    }
}
