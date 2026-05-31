<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\VerificationCode;
use App\Message\SendTelegramConfirmationMessage;
use App\Message\SendVerificationCodeMessage;
use App\Repository\UserRepository;
use App\Repository\VerificationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class VerificationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly VerificationCodeRepository $codeRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/verify', name: 'app_verify', methods: ['GET', 'POST'])]
    public function verify(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('_verification_user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->userRepository->find($userId);

        if (!$user || $user->isVerified()) {
            $session->remove('_verification_user_id');
            return $this->redirectToRoute('app_login');
        }

        $error = null;
        $blocked = false;

        $code = $this->codeRepository->findPendingForUser($user);

        if ($request->isMethod('POST')) {
            if (!$code) {
                $error = 'Код подтверждения не найден или уже использован.';
                $blocked = true;
            } elseif ($code->isMaxAttemptsReached()) {
                $code->setStatus(VerificationCode::STATUS_EXPIRED);
                $this->em->flush();
                $error = 'Превышено максимальное количество попыток. Код заблокирован.';
                $blocked = true;
            } else {
                $submitted = trim($request->request->get('code', ''));

                if ($submitted !== $code->getCode()) {
                    $code->incrementAttempts();

                    if ($code->isMaxAttemptsReached()) {
                        $code->setStatus(VerificationCode::STATUS_EXPIRED);
                        $this->em->flush();
                        $error = 'Превышено максимальное количество попыток. Код заблокирован.';
                        $blocked = true;
                    } else {
                        $this->em->flush();
                        $remaining = VerificationCode::MAX_ATTEMPTS - $code->getAttemptCount();
                        $error = sprintf('Неверный код. Осталось попыток: %d.', $remaining);
                    }
                } else {
                    $user->setIsVerified(true);
                    $code->setStatus(VerificationCode::STATUS_USED);
                    $code->setConfirmedAt(new \DateTime());
                    $session->remove('_verification_user_id');
                    $this->em->flush();

                    $this->addFlash('success', 'Email успешно подтверждён. Добро пожаловать!');
                    return $this->redirectToRoute('app_login');
                }
            }
        } elseif ($code && $code->isMaxAttemptsReached()) {
            $code->setStatus(VerificationCode::STATUS_EXPIRED);
            $this->em->flush();
            $blocked = true;
            $error = 'Превышено максимальное количество попыток. Код заблокирован.';
        }

        $latest = $this->codeRepository->findLatestForUser($user);
        $secondsUntilResend = $latest ? $latest->getSecondsUntilResend() : 0;

        return $this->render('security/verify.html.twig', [
            'user'              => $user,
            'error'             => $error,
            'blocked'           => $blocked,
            'attemptsLeft'      => $code ? max(0, VerificationCode::MAX_ATTEMPTS - $code->getAttemptCount()) : 0,
            'secondsUntilResend' => $secondsUntilResend,
        ]);
    }

    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['POST'])]
    public function resend(Request $request, MessageBusInterface $bus): Response
    {
        $session = $request->getSession();
        $userId = $session->get('_verification_user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->userRepository->find($userId);

        if (!$user || $user->isVerified()) {
            $session->remove('_verification_user_id');
            return $this->redirectToRoute('app_login');
        }

        $existing = $this->codeRepository->findLatestForUser($user);

        if ($existing && !$existing->canResend()) {
            $this->addFlash('error', sprintf(
                'Подождите %d сек. перед повторной отправкой.',
                $existing->getSecondsUntilResend()
            ));
            return $this->redirectToRoute('app_verify');
        }

        $newCode = (string) random_int(100000, 999999);

        if ($existing) {
            $existing->resetForResend($newCode);
        } else {
            $existing = new VerificationCode($user, $newCode);
            $existing->setSentAt(new \DateTimeImmutable());
            $this->em->persist($existing);
        }

        $this->em->flush();
        $bus->dispatch(new SendVerificationCodeMessage($user->getId(), $newCode));
        $bus->dispatch(new SendTelegramConfirmationMessage($user->getId(), $existing->getId()));

        $this->addFlash('info', sprintf('Новый код подтверждения: %s', $newCode));
        return $this->redirectToRoute('app_verify');
    }
}
