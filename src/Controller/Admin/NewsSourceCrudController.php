<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NewsSource;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class NewsSourceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NewsSource::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name', 'Название'),
            UrlField::new('url', 'URL'),
            ChoiceField::new('type', 'Тип')->setChoices([
                'RSS'  => NewsSource::TYPE_RSS,
                'API'  => NewsSource::TYPE_API,
                'HTML' => NewsSource::TYPE_HTML,
            ]),
            AssociationField::new('defaultCategory', 'Категория по умолчанию'),
            BooleanField::new('isActive', 'Активен'),
            DateTimeField::new('lastFetchedAt', 'Последний импорт')->hideOnForm(),
        ];
    }
}
