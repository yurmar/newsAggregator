<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class  => 'onLoginSuccess',
            UserRegisteredEvent::class => 'onUserRegistered',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $name = method_exists($user, 'getName') ? $user->getName() : $user->getUserIdentifier();

        $this->notificationService->broadcast(
            'user_login',
            sprintf('Пользователь «%s» вошёл в систему', $name),
        );
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->notificationService->broadcast(
            'user_registered',
            sprintf('Зарегистрирован новый пользователь «%s»', $event->userName),
        );
    }
}
