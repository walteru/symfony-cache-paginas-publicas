<?php

namespace App\Entity;

use App\Repository\SuscriptorRepository;
use Doctrine\ORM\Mapping as ORM;

/** Alguien que recibe un mail cada vez que se publica un artículo. */
#[ORM\Entity(repositoryClass: SuscriptorRepository::class)]
class Suscriptor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    public function __construct(string $email = '')
    {
        $this->email = $email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
}
