<?php

namespace App\Controller;

use App\Entity\Articulo;
use App\Repository\ArticuloRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vista PÚBLICA de los artículos publicados. Es lo que cachea el demo:
 *
 *  - Sin sesión, sin CSRF, sin acciones de edición. Una página "anónima" pura,
 *    que el reverse proxy puede compartir entre todos los visitantes.
 *  - El contador de vistas es el único bit dinámico → va por ESI y queda fuera
 *    del cache de la página.
 *
 * El backoffice editorial vive en `/articulos/...` (ArticuloController) y NO
 * se cachea: tiene formularios, CSRF y rol en sesión. Mezclarlos en una misma
 * URL pública sería pegarse un tiro: Cache-Control: public con cookies = roto.
 */
class PublicoController extends AbstractController
{
    private const TTL_PUBLICO = 60; // segundos

    public function __construct(private ArticuloRepository $articulos)
    {
    }

    #[Route('/p/articulos', name: 'publico_listar', methods: ['GET'])]
    public function listar(): Response
    {
        $response = $this->render('publico/index.html.twig', [
            'articulos' => $this->articulos->publicados(),
        ]);

        // public + s-maxage: el reverse proxy (HttpCache) la reutiliza durante
        // TTL_PUBLICO segundos sin pedirle nada al backend.
        $response->setPublic();
        $response->setSharedMaxAge(self::TTL_PUBLICO);

        return $response;
    }

    #[Route('/p/articulos/{id}', name: 'publico_ver', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function ver(Articulo $articulo): Response
    {
        // Solo damos vista pública a los artículos publicados; el resto no
        // existe para el mundo. 404 (no 403) para no filtrar la existencia.
        if ($articulo->getEstado() !== 'publicado') {
            throw $this->createNotFoundException();
        }

        $response = $this->render('publico/ver.html.twig', [
            'articulo' => $articulo,
        ]);
        $response->setPublic();
        $response->setSharedMaxAge(self::TTL_PUBLICO);

        return $response;
    }

    /**
     * Fragmento ESI: se monta dentro de `publico_ver` con render_esi().
     * El reverse proxy resuelve este sub-request en CADA hit del usuario,
     * mientras la página exterior sigue siendo cache hit. Por eso el contador
     * está siempre fresco aunque la página sea vieja.
     */
    #[Route('/p/articulos/{id}/vistas', name: 'publico_vistas', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function vistas(Articulo $articulo): Response
    {
        if ($articulo->getEstado() !== 'publicado') {
            throw $this->createNotFoundException();
        }

        $articulo->incrementarVistas();
        $this->articulos->save($articulo);

        $response = $this->render('publico/_vistas.html.twig', [
            'vistas' => $articulo->getVistas(),
        ]);

        // El fragmento NO se cachea: cada visita lo regenera. Es lo opuesto al
        // resto de la página.
        $response->headers->set('Cache-Control', 'no-store, max-age=0');

        return $response;
    }
}
