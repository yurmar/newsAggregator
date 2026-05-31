<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\VerificationCode;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_article_index');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Symfony перехватывает этот маршрут автоматически
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_article_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->persist($user);

            $code = new VerificationCode($user, (string) random_int(100000, 999999));
            $em->persist($code);
            $em->flush();

            $request->getSession()->set('_verification_user_id', $user->getId());

            $this->addFlash('info', sprintf(
                'Регистрация прошла успешно! Ваш код подтверждения: %s',
                $code->getCode()
            ));
            return $this->redirectToRoute('app_verify');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('security/profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
