<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'app_article_')]
class ArticleController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $categorySlug = $request->query->getString('category');
        $category = $categorySlug ? $this->categoryRepository->findOneBy(['slug' => $categorySlug]) : null;

        $articles = $this->articleRepository->findPaginated($page, 20, $category);
        $totalPages = (int) ceil(count($articles) / 20);

        return $this->render('article/index.html.twig', [
            'articles' => $articles,
            'categories' => $this->categoryRepository->findAll(),
            'currentCategory' => $category,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('article/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $article = $this->articleRepository->find($id);
        if (!$article) {
            throw $this->createNotFoundException('Статья не найдена');
        }

        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }
}
