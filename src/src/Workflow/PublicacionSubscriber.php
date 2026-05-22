<?php

namespace App\Workflow;

use App\Entity\Articulo;
use App\Message\NotificarSuscriptores;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Acá vive la lógica que el grafo de estados (workflow.yaml) NO puede expresar:
 *
 *  - GUARDS: vetar una transición según una condición de negocio o de permisos.
 *    El grafo dice "de en_revision se puede aprobar"; el guard dice
 *    "...pero solo si sos editor".
 *
 *  - Eventos ENTERED: efectos secundarios al entrar a un lugar
 *    (acá: sellar la fecha de publicación y AVISAR a los suscriptores).
 *
 * Nombres de los eventos: workflow.<nombre>.<tipo>[.<transicion|lugar>]
 */
class PublicacionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RolActual $rol,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * No se manda a revisión un artículo demasiado corto. Es una regla de
     * negocio, no de estructura: por eso va en un guard y no en el grafo.
     */
    public function guardEnviarARevision(GuardEvent $event): void
    {
        /** @var Articulo $articulo */
        $articulo = $event->getSubject();

        if (mb_strlen(trim($articulo->getContenido())) < 50) {
            $event->setBlocked(true, 'El artículo es demasiado corto para revisión (mínimo 50 caracteres).');
        }
    }

    /** Solo un editor aprueba o rechaza. Un autor no puede revisar lo suyo. */
    public function guardSoloEditor(GuardEvent $event): void
    {
        if (!$this->rol->esEditor()) {
            $event->setBlocked(true, 'Solo un editor puede aprobar o rechazar. Cambiá de rol arriba a la derecha.');
        }
    }

    /**
     * Al entrar a "publicado": sellamos la fecha y avisamos a los suscriptores.
     *
     * Pero NO mandamos los mails acá (sería trabajo lento dentro del request).
     * Solo DESPACHAMOS un mensaje: lo encolamos y seguimos. El editor ve
     * "publicado" al instante; los mails los manda el worker en segundo plano.
     */
    public function alPublicar(EnteredEvent $event): void
    {
        /** @var Articulo $articulo */
        $articulo = $event->getSubject();
        $articulo->setPublicadoEl(new \DateTimeImmutable());

        $this->bus->dispatch(new NotificarSuscriptores($articulo->getId()));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.publicacion.guard.enviar_a_revision' => 'guardEnviarARevision',
            'workflow.publicacion.guard.aprobar' => 'guardSoloEditor',
            'workflow.publicacion.guard.rechazar' => 'guardSoloEditor',
            'workflow.publicacion.entered.publicado' => 'alPublicar',
        ];
    }
}
