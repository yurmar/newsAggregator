<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
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
        $message = sprintf('Зарегистрирован новый пользователь «%s»', $event->userName);

        foreach ($this->userRepository->findAll() as $user) {
            $this->notificationService->notify($user, $message, 'info');
        }

        $this->notificationService->broadcast('user_registered', $message);
    }
}
