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

    private function debug(string $msg): void
    {
        $line = sprintf("[%s] [Telegram] %s\n", date('Y-m-d H:i:s'), $msg);
        fwrite(STDERR, $line);
    }

    public function __invoke(SendTelegramConfirmationMessage $message): void
    {
        $this->debug(sprintf('Handler invoked. userId=%d codeId=%d', $message->userId, $message->confirmationCodeId));

        $user = $this->userRepository->find($message->userId);
        $code = $this->codeRepository->find($message->confirmationCodeId);

        if (!$user || !$code) {
            $this->debug(sprintf('NOT FOUND: user=%s code=%s', $user ? 'ok' : 'NULL', $code ? 'ok' : 'NULL'));
            $this->logger->error('[Telegram] User or code not found', [
                'userId'             => $message->userId,
                'confirmationCodeId' => $message->confirmationCodeId,
            ]);
            return;
        }

        $this->debug(sprintf('User=%s codeValue=%s chatId=%s tokenPrefix=%s',
            $user->getEmail(), $code->getCode(), $this->chatId, substr($this->botToken, 0, 8)
        ));

        $text = sprintf(
            "Привет, %s!\n\nВаш код подтверждения: *%s*\n\nКод действителен 10 минут.",
            $user->getName(),
            $code->getCode(),
        );

        try {
            $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken);
            $payload = [
                'chat_id'    => $this->chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown',
            ];

            $this->debug('Sending HTTP request to Telegram...');

            $response = $this->httpClient->request('POST', $url, ['json' => $payload]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);

            $this->debug(sprintf('Response: status=%d body=%s', $statusCode, $responseBody));

            if ($statusCode !== 200) {
                throw new \RuntimeException(sprintf(
                    'Telegram API error %d: %s',
                    $statusCode,
                    $responseBody,
                ));
            }

            $code->setStatus(VerificationCode::STATUS_SENT);
            $code->setSentAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->debug('SUCCESS — status updated to SENT');

        } catch (\Throwable $e) {
            $this->debug(sprintf('EXCEPTION: %s — %s', get_class($e), $e->getMessage()));
            $this->logger->error('[Telegram] Failed to send confirmation code', [
                'userId'             => $message->userId,
                'confirmationCodeId' => $message->confirmationCodeId,
                'error'              => $e->getMessage(),
                'exceptionClass'     => get_class($e),
            ]);

            $code->setStatus(VerificationCode::STATUS_FAILED);
            $this->em->flush();

            throw $e;
        }
    }
}
