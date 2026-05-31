<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications', name: 'app_notification_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $notifications = $this->notificationRepository->findUnreadByUser($this->getUser());

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['POST'])]
    public function markRead(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $notification = $this->notificationRepository->find($id);
        if ($notification && $notification->getUser() === $this->getUser()) {
            $notification->setIsRead(true);
            $this->em->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/mark-all-read', name: 'mark_all_read', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $notifications = $this->notificationRepository->findUnreadByUser($this->getUser());
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }
        $this->em->flush();

        return $this->json(['success' => true, 'count' => count($notifications)]);
    }

    #[Route('/count', name: 'count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json([
            'count' => $this->notificationRepository->countUnreadByUser($this->getUser()),
        ]);
    }
}
