<?php

namespace App\Controller;

use App\Entity\Articulo;
use App\Repository\ArticuloRepository;
use App\Repository\NotificacionRepository;
use App\Repository\SuscriptorRepository;
use App\Workflow\RolActual;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

class ArticuloController extends AbstractController
{
    // El parámetro $publicacionStateMachine lo autowirea Symfony a partir
    // del nombre del workflow ("publicacion") + el tipo (state_machine).
    public function __construct(
        private WorkflowInterface $publicacionStateMachine,
        private ArticuloRepository $articulos,
        private SuscriptorRepository $suscriptores,
        private NotificacionRepository $notificaciones,
        private RolActual $rol,
    ) {
    }

    #[Route('/', name: 'inicio')]
    public function inicio(): Response
    {
        return $this->redirectToRoute('articulos_listar');
    }

    #[Route('/articulos', name: 'articulos_listar', methods: ['GET'])]
    public function listar(): Response
    {
        return $this->render('articulo/index.html.twig', [
            'articulos' => $this->articulos->ultimos(),
            'rol' => $this->rol->get(),
        ]);
    }

    #[Route('/articulos/nuevo', name: 'articulos_nuevo', methods: ['GET', 'POST'])]
    public function nuevo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $articulo = new Articulo();
            $articulo->setTitulo(trim($request->request->get('titulo', '')) ?: 'Sin título');
            $articulo->setContenido(trim($request->request->get('contenido', '')));
            $articulo->setAutor(trim($request->request->get('autor', '')) ?: 'Anónimo');
            $this->articulos->save($articulo);

            return $this->redirectToRoute('articulos_ver', ['id' => $articulo->getId()]);
        }

        return $this->render('articulo/nuevo.html.twig');
    }

    #[Route('/articulos/{id}', name: 'articulos_ver', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function ver(Articulo $articulo): Response
    {
        // getEnabledTransitions ya respeta los guards: si sos "autor",
        // aprobar/rechazar no aparecen porque su guard las bloquea.
        $transiciones = $this->publicacionStateMachine->getEnabledTransitions($articulo);

        return $this->render('articulo/ver.html.twig', [
            'articulo' => $articulo,
            'transiciones' => $transiciones,
            'rol' => $this->rol->get(),
            // Las notificaciones las crea el WORKER en segundo plano: aparecen
            // acá segundos después de publicar (refrescando la página).
            'notificaciones' => $this->notificaciones->deArticulo($articulo->getId()),
        ]);
    }

    #[Route('/articulos/{id}/transicion/{transicion}', name: 'articulos_transicion', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function aplicarTransicion(Request $request, Articulo $articulo, string $transicion): Response
    {
        if (!$this->isCsrfTokenValid('transicion'.$articulo->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');

            return $this->redirectToRoute('articulos_ver', ['id' => $articulo->getId()]);
        }

        if ($transicion === 'rechazar') {
            $motivo = trim($request->request->get('motivo', ''));
            $articulo->setMotivoRechazo($motivo !== '' ? $motivo : 'Sin motivo indicado.');
        }

        try {
            // apply() chequea los guards, mueve el estado y dispara los eventos.
            // Si algún guard bloquea, lanza LogicException con su mensaje.
            $this->publicacionStateMachine->apply($articulo, $transicion);
            $this->articulos->save($articulo);

            if ($transicion === 'publicar') {
                // El dispatch ya ocurrió (en el evento entered.publicado) y este
                // request respondió sin esperar a los mails: por eso el mensaje
                // habla de "en segundo plano".
                $this->addFlash('exito', sprintf(
                    'Publicado. Encolé la notificación a %d suscriptores; el worker los está avisando en segundo plano. Refrescá en unos segundos para verlas aparecer.',
                    $this->suscriptores->count([]),
                ));
            } else {
                $this->addFlash('exito', sprintf('Transición «%s» aplicada.', $transicion));
            }
        } catch (LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('articulos_ver', ['id' => $articulo->getId()]);
    }

    #[Route('/rol/{rol}', name: 'cambiar_rol', methods: ['GET'])]
    public function cambiarRol(string $rol, Request $request): Response
    {
        $this->rol->set($rol);

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('articulos_listar'));
    }
}
