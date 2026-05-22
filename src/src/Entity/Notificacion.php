<?php

namespace App\Entity;

use App\Repository\NotificacionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registro de que se notificó a un suscriptor sobre un artículo. No es parte
 * del dominio "editorial": existe para HACER VISIBLE el trabajo del worker.
 * El timestamp enviadoEl muestra que esto ocurrió DESPUÉS de que el request
 * de "publicar" ya había respondido.
 */
#[ORM\Entity(repositoryClass: NotificacionRepository::class)]
class Notificacion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $articuloId;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $enviadoEl;

    public function __construct(int $articuloId, string $email)
    {
        $this->articuloId = $articuloId;
        $this->email = $email;
        $this->enviadoEl = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticuloId(): int
    {
        return $this->articuloId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEnviadoEl(): \DateTimeImmutable
    {
        return $this->enviadoEl;
    }
}
