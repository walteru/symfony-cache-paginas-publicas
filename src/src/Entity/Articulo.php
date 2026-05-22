<?php

namespace App\Entity;

use App\Repository\ArticuloRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticuloRepository::class)]
class Articulo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $titulo = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $contenido = '';

    /**
     * El "lugar" actual dentro del workflow. El componente Workflow lee y
     * escribe ESTA propiedad (marking_store property: estado). Nunca la
     * tocamos a mano: la mueve el workflow al aplicar una transición.
     */
    #[ORM\Column(length: 20)]
    private string $estado = 'borrador';

    #[ORM\Column(length: 100)]
    private string $autor = 'Anónimo';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creadoEl;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publicadoEl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motivoRechazo = null;

    /**
     * Contador de vistas de la página pública. Se incrementa en CADA request
     * vía un fragmento ESI: por eso este número es lo único que no se cachea
     * con el resto de la página.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $vistas = 0;

    public function __construct()
    {
        $this->creadoEl = new \DateTimeImmutable();
    }

    public function getVistas(): int
    {
        return $this->vistas;
    }

    public function incrementarVistas(): self
    {
        $this->vistas++;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): self
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getContenido(): string
    {
        return $this->contenido;
    }

    public function setContenido(string $contenido): self
    {
        $this->contenido = $contenido;

        return $this;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): self
    {
        $this->estado = $estado;

        return $this;
    }

    public function getAutor(): string
    {
        return $this->autor;
    }

    public function setAutor(string $autor): self
    {
        $this->autor = $autor;

        return $this;
    }

    public function getCreadoEl(): \DateTimeImmutable
    {
        return $this->creadoEl;
    }

    public function getPublicadoEl(): ?\DateTimeImmutable
    {
        return $this->publicadoEl;
    }

    public function setPublicadoEl(?\DateTimeImmutable $publicadoEl): self
    {
        $this->publicadoEl = $publicadoEl;

        return $this;
    }

    public function getMotivoRechazo(): ?string
    {
        return $this->motivoRechazo;
    }

    public function setMotivoRechazo(?string $motivoRechazo): self
    {
        $this->motivoRechazo = $motivoRechazo;

        return $this;
    }
}
