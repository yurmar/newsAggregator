<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findPaginated(int $page, int $perPage = 20, ?Category $category = null): Paginator
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.source', 's')
            ->leftJoin('a.category', 'c')
            ->addSelect('s', 'c')
            ->orderBy('a.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($category !== null) {
            $qb->where('a.category = :category')->setParameter('category', $category);
        }

        return new Paginator($qb);
    }

    /**
     * Full-text search across title, summary and content.
     * Uses MySQL FULLTEXT index when available, falls back to LIKE.
     *
     * @return array<array<string, mixed>>
     */
    public function search(string $query, int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $likeParam = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';

        $safeLimit = (int) $limit;

        if (mb_strlen($query) >= 3) {
            try {
                // Build BOOLEAN MODE query: each word gets a prefix wildcard for partial matching
                $words = array_filter(preg_split('/\s+/', trim($query)));
                $ftQuery = implode(' ', array_map(
                    static fn(string $w) => preg_replace('/[+\-><()~*"@\\\\]/', '', $w) . '*',
                    $words,
                ));

                return $conn->executeQuery(
                    'SELECT a.id, a.title, a.summary, a.image_url, a.published_at,
                            s.name AS source_name, c.name AS category_name
                     FROM articles a
                     LEFT JOIN news_sources s ON a.source_id = s.id
                     LEFT JOIN categories c ON a.category_id = c.id
                     WHERE MATCH(a.title, a.summary, a.content) AGAINST(? IN BOOLEAN MODE)
                     ORDER BY MATCH(a.title, a.summary, a.content) AGAINST(?) DESC
                     LIMIT ' . $safeLimit,
                    [$ftQuery, $ftQuery],
                )->fetchAllAssociative();
            } catch (\Exception) {
                // FULLTEXT index not available — fall through to LIKE
            }
        }

        return $conn->executeQuery(
            'SELECT a.id, a.title, a.summary, a.image_url, a.published_at,
                    s.name AS source_name, c.name AS category_name
             FROM articles a
             LEFT JOIN news_sources s ON a.source_id = s.id
             LEFT JOIN categories c ON a.category_id = c.id
             WHERE a.title LIKE ? OR a.summary LIKE ? OR a.content LIKE ?
             ORDER BY a.published_at DESC
             LIMIT ' . $safeLimit,
            [$likeParam, $likeParam, $likeParam],
        )->fetchAllAssociative();
    }

    public function existsByUrl(string $url): bool
    {
        return (bool) $this->createQueryBuilder('a')
            ->select('1')
            ->where('a.externalUrl = :url')
            ->setParameter('url', $url)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Article $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
