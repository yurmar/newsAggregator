<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\VerificationCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VerificationCode>
 */
class VerificationCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationCode::class);
    }

    public function findPendingForUser(User $user): ?VerificationCode
    {
        return $this->createQueryBuilder('v')
            ->where('v.user = :user')
            ->andWhere('v.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', VerificationCode::STATUS_PENDING)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
