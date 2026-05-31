<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NewsSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsSource::class);
    }

    /** @return NewsSource[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->getQuery()
            ->getResult();
    }

    public function save(NewsSource $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
