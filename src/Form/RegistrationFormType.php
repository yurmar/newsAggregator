<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Имя',
                'attr' => ['placeholder' => 'Ваше имя', 'class' => 'form-control'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['placeholder' => 'email@example.com', 'class' => 'form-control'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Пароль',
                    'attr' => ['placeholder' => 'Минимум 6 символов', 'class' => 'form-control'],
                ],
                'second_options' => [
                    'label' => 'Повтор пароля',
                    'attr' => ['placeholder' => 'Повторите пароль', 'class' => 'form-control'],
                ],
                'invalid_message' => 'Пароли должны совпадать',
                'constraints' => [
                    new NotBlank(['message' => 'Введите пароль']),
                    new Length(['min' => 6, 'minMessage' => 'Пароль должен быть не менее {{ limit }} символов']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
