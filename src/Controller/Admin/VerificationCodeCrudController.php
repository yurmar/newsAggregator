<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\VerificationCode;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class VerificationCodeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return VerificationCode::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('code', 'Код подтверждения'),
            AssociationField::new('user', 'Пользователь'),
            ChoiceField::new('status', 'Статус отправки')
                ->setChoices([
                    'Ожидание' => VerificationCode::STATUS_PENDING,
                    'Отправлен' => VerificationCode::STATUS_SENT,
                    'Ошибка' => VerificationCode::STATUS_FAILED,
                    'Использован' => VerificationCode::STATUS_USED,
                    'Истёк' => VerificationCode::STATUS_EXPIRED,
                ])
                ->renderAsBadges([
                    VerificationCode::STATUS_PENDING => 'warning',
                    VerificationCode::STATUS_SENT => 'info',
                    VerificationCode::STATUS_FAILED => 'danger',
                    VerificationCode::STATUS_USED => 'success',
                    VerificationCode::STATUS_EXPIRED => 'secondary',
                ]),
            IntegerField::new('attemptCount', 'Попытки')->hideOnIndex(),
            DateTimeField::new('createdAt', 'Дата создания'),
            DateTimeField::new('sentAt', 'Дата отправки'),
            DateTimeField::new('confirmedAt', 'Дата подтверждения'),
        ];
    }
}
