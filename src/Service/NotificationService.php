<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ?HubInterface $mercureHub = null,
    ) {}

    public function notify(User $user, string $message, string $type = 'info'): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);
        $notification->setType($type);
        $this->em->persist($notification);
        $this->em->flush();

        $this->publishToMercure($user, $notification);

        return $notification;
    }

    public function broadcast(string $type, string $message): void
    {
        if ($this->mercureHub === null) {
            return;
        }

        try {
            $update = new Update(
                '/notifications/broadcast',
                json_encode(['type' => $type, 'message' => $message], JSON_THROW_ON_ERROR),
            );
            $this->mercureHub->publish($update);
        } catch (\Throwable) {
            // Mercure недоступен
        }
    }

    private function publishToMercure(User $user, Notification $notification): void
    {
        if ($this->mercureHub === null) {
            return;
        }

        try {
            $update = new Update(
                sprintf('/notifications/user/%d', $user->getId()),
                json_encode([
                    'id' => $notification->getId(),
                    'message' => $notification->getMessage(),
                    'type' => $notification->getType(),
                    'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ], JSON_THROW_ON_ERROR),
                private: true,
            );
            $this->mercureHub->publish($update);
        } catch (\Throwable) {
            // Mercure недоступен — уведомление уже сохранено в БД, не падаем
        }
    }
}
