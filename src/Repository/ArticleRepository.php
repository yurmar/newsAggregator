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
