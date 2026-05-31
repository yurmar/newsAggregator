<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Article;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ArticleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Article::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title', 'Заголовок'),
            AssociationField::new('category', 'Категория'),
            AssociationField::new('source', 'Источник'),
            TextareaField::new('summary', 'Краткое описание')->hideOnIndex(),
            UrlField::new('externalUrl', 'Ссылка'),
            UrlField::new('imageUrl', 'Изображение')->hideOnIndex(),
            DateTimeField::new('publishedAt', 'Опубликовано'),
            DateTimeField::new('createdAt', 'Добавлено')->hideOnForm(),
        ];
    }
}
