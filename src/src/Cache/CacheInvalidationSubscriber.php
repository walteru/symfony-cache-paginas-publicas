<?php

namespace App\Cache;

use App\Entity\Articulo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Invalida el reverse-proxy cache cuando el contenido público de un artículo
 * cambia. Compone con el resto de la serie sobre el mismo evento:
 *
 *  - #1 Workflow: sella la fecha de publicación.
 *  - #2 Messenger: encola la notificación a suscriptores.
 *  - #3 Cache  (acá): purga las páginas públicas afectadas.
 *
 * Tres efectos sobre la misma transición. El controller no sabe nada de esto.
 */
class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Store $store,
        private RequestStack $requestStack,
    ) {
    }

    public function alPublicar(EnteredEvent $event): void
    {
        /** @var Articulo $articulo */
        $articulo = $event->getSubject();

        // El listado público gana una entrada nueva → invalidar.
        $this->purge('/p/articulos');

        // La página pública del artículo recién publicado pasa a existir →
        // si por alguna razón hubiera quedado cacheado un 404, lo limpiamos.
        $this->purge('/p/articulos/'.$articulo->getId());
    }

    /**
     * Symfony HttpCache indexa por URL ABSOLUTA (con scheme + host + puerto),
     * así que reconstruimos esa URL a partir del request en curso. En CLI no
     * hay request: en ese caso no purgamos (las transiciones por consola en
     * este demo no afectan páginas servidas, y un purge sin host no matchea).
     */
    private function purge(string $path): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return;
        }
        $this->store->purge($request->getSchemeAndHttpHost().$path);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.publicacion.entered.publicado' => 'alPublicar',
        ];
    }
}
