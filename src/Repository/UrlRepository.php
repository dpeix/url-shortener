<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Url;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Url>
 */
final class UrlRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Url::class);
    }

    public function findByShortCode(string $code): ?Url
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.shortCode = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Url[]
     */
    public function findExpiredUrls(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.expiresAt IS NOT NULL')
            ->andWhere('u.expiresAt < :now')
            ->andWhere('u.isActive = :active')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}
