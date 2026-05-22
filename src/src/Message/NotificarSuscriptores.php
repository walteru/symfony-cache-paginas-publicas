<?php

namespace App\Message;

/**
 * Un MENSAJE es un objeto plano, sin lógica: solo describe *algo que hay que
 * hacer*. No sabe cómo hacerlo (eso es trabajo del handler). Por eso lleva el
 * mínimo de datos para reconstruir el contexto del otro lado: el id del
 * artículo. Va serializado a la cola, así que mantenelo chico y serializable
 * (un id, no la entidad entera).
 */
final class NotificarSuscriptores
{
    public function __construct(
        public readonly int $articuloId,
    ) {
    }
}
