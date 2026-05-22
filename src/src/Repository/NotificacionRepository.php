<?php

namespace App\Repository;

use App\Entity\Notificacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notificacion>
 */
class NotificacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notificacion::class);
    }

    public function save(Notificacion $notificacion, bool $flush = true): void
    {
        $this->getEntityManager()->persist($notificacion);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * @return Notificacion[]
     */
    public function deArticulo(int $articuloId): array
    {
        return $this->findBy(['articuloId' => $articuloId], ['enviadoEl' => 'ASC']);
    }
}
