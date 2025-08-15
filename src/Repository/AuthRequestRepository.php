<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthRequest::class);
    }

    public function findOneByIdAndNotExpired(string $id): ?AuthRequest
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.id = :id')
            ->andWhere('t.expired >= :now')
            ->setParameter('id', $id)
            ->setParameter('now', new \DateTime());

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function updateExpiredToNow(string $id): void
    {
        $qb = $this->createQueryBuilder('t')
            ->update()
            ->set('t.expired', ':now')
            ->where('t.userId = :identifier')
            ->andWhere('t.expired >= :now')
            ->setParameter('identifier', $id)
            ->setParameter('now', new \DateTime());

        $qb->getQuery()->execute();
    }
}
