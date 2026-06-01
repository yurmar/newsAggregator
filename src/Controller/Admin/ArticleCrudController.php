<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Article;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['publishedAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title', 'Заголовок'),
            AssociationField::new('source', 'Источник'),
            AssociationField::new('category', 'Категория')->hideOnIndex(),
            TextareaField::new('summary', 'Краткое описание')->hideOnIndex(),
            UrlField::new('externalUrl', 'Ссылка')->hideOnIndex(),
            UrlField::new('imageUrl', 'Изображение')->hideOnIndex(),
            DateTimeField::new('publishedAt', 'Дата публикации'),
            DateTimeField::new('createdAt', 'Дата получения')->hideOnForm(),
        ];
    }
}
