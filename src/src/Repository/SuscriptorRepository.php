<?php

namespace App\Repository;

use App\Entity\Suscriptor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Suscriptor>
 */
class SuscriptorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Suscriptor::class);
    }

    public function save(Suscriptor $suscriptor, bool $flush = true): void
    {
        $this->getEntityManager()->persist($suscriptor);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Suscriptor[]
     */
    public function todos(): array
    {
        return $this->findBy([], ['email' => 'ASC']);
    }
}
