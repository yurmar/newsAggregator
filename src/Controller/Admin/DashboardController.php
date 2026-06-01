<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\NewsSource;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\VerificationCode;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/admin', name: 'admin_dashboard')]
    public function index(): Response
    {
        $stats = [
            'articles'        => $this->em->getRepository(Article::class)->count([]),
            'sources'         => $this->em->getRepository(NewsSource::class)->count([]),
            'sourcesActive'   => $this->em->getRepository(NewsSource::class)->count(['isActive' => true]),
            'users'           => $this->em->getRepository(User::class)->count([]),
            'usersVerified'   => $this->em->getRepository(User::class)->count(['isVerified' => true]),
            'codes'           => $this->em->getRepository(VerificationCode::class)->count([]),
            'codesPending'    => $this->em->getRepository(VerificationCode::class)->count(['status' => VerificationCode::STATUS_PENDING]),
            'categories'      => $this->em->getRepository(Category::class)->count([]),
        ];

        return $this->render('admin/dashboard.html.twig', ['stats' => $stats]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('News Aggregator Admin')
            ->setFaviconPath('favicon.ico');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('/ea/admin.css');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->addMenuItems([
                MenuItem::linkToRoute('На главную сайта', 'fa fa-home', 'app_article_index'),
            ]);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Панель управления', 'fa fa-home');
        yield MenuItem::section('Контент');
        yield MenuItem::linkToCrud('Статьи', 'fa fa-newspaper', Article::class);
        yield MenuItem::linkToCrud('Категории', 'fa fa-tags', Category::class);
        yield MenuItem::section('Источники');
        yield MenuItem::linkToCrud('Источники новостей', 'fa fa-rss', NewsSource::class);
        yield MenuItem::section('Пользователи');
        yield MenuItem::linkToCrud('Пользователи', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Коды подтверждения', 'fa fa-key', VerificationCode::class);
        yield MenuItem::linkToCrud('Уведомления', 'fa fa-bell', Notification::class);
        yield MenuItem::section('Сайт');
        yield MenuItem::linkToRoute('На сайт', 'fa fa-external-link', 'app_article_index')->setLinkTarget('_blank');
    }
}
