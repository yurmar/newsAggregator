<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\NewsSource;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class NewsSourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название',
                'attr' => ['placeholder' => 'Например: Hacker News', 'class' => 'form-input'],
                'constraints' => [new NotBlank(message: 'Введите название')],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL источника',
                'attr' => ['placeholder' => 'https://...', 'class' => 'form-input'],
                'constraints' => [
                    new NotBlank(message: 'Введите URL'),
                    new Url(message: 'Некорректный URL'),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип',
                'choices' => [
                    'RSS / Atom' => NewsSource::TYPE_RSS,
                    'API (JSON)' => NewsSource::TYPE_API,
                    'HTML-парсинг' => NewsSource::TYPE_HTML,
                ],
                'attr' => ['class' => 'form-input'],
            ])
            ->add('defaultCategory', EntityType::class, [
                'label' => 'Категория по умолчанию',
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Без категории',
                'attr' => ['class' => 'form-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewsSource::class,
        ]);
    }
}
