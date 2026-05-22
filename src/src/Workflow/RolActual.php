<?php

namespace App\Workflow;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * En una app real esto sería el usuario autenticado y sus roles (Security).
 * Para la demo lo simulamos con un valor en sesión que se cambia con un botón,
 * así se ve cómo un guard depende de QUIÉN intenta la transición sin montar
 * todo el sistema de login.
 */
class RolActual
{
    private const CLAVE = 'rol_demo';
    public const AUTOR = 'autor';
    public const EDITOR = 'editor';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function get(): string
    {
        return $this->requestStack->getSession()->get(self::CLAVE, self::AUTOR);
    }

    public function set(string $rol): void
    {
        $rol = $rol === self::EDITOR ? self::EDITOR : self::AUTOR;
        $this->requestStack->getSession()->set(self::CLAVE, $rol);
    }

    public function esEditor(): bool
    {
        return $this->get() === self::EDITOR;
    }
}
