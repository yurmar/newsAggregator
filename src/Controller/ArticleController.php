<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/', name: 'app_article_')]
#[IsGranted('ROLE_USER')]
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

        $articles = $this->articleRepository->findPaginated($page, 10, $category);
        $totalPages = (int) ceil(count($articles) / 10);

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

    #[Route('search', name: 'search')]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q'));

        if (mb_strlen($query) < 2) {
            return $this->json(['articles' => [], 'query' => $query, 'total' => 0]);
        }

        $articles = $this->articleRepository->search($query);

        return $this->json([
            'articles' => $articles,
            'query' => $query,
            'total' => count($articles),
        ]);
    }
}
