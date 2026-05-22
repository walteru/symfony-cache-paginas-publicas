<?php

namespace App\Repository;

use App\Entity\Articulo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Articulo>
 */
class ArticuloRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Articulo::class);
    }

    public function save(Articulo $articulo, bool $flush = true): void
    {
        $this->getEntityManager()->persist($articulo);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Articulo[]
     */
    public function ultimos(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.creadoEl', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Solo los publicados — lo que ve el mundo en la vista pública.
     *
     * @return Articulo[]
     */
    public function publicados(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.estado = :p')->setParameter('p', 'publicado')
            ->orderBy('a.publicadoEl', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
