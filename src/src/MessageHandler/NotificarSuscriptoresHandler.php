<?php

namespace App\MessageHandler;

use App\Entity\Notificacion;
use App\Message\NotificarSuscriptores;
use App\Repository\ArticuloRepository;
use App\Repository\NotificacionRepository;
use App\Repository\SuscriptorRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * El HANDLER es el código que sabe procesar el mensaje. Symfony lo conecta con
 * NotificarSuscriptores por el type-hint del argumento de __invoke(): no hay
 * que registrar nada a mano (gracias a #[AsMessageHandler] + autoconfigure).
 *
 * Esto NO corre en el request: corre en el worker (messenger:consume), después
 * de que el usuario ya recibió su respuesta. Acá vive el trabajo lento.
 */
#[AsMessageHandler]
final class NotificarSuscriptoresHandler
{
    public function __construct(
        private ArticuloRepository $articulos,
        private SuscriptorRepository $suscriptores,
        private NotificacionRepository $notificaciones,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotificarSuscriptores $mensaje): void
    {
        $articulo = $this->articulos->find($mensaje->articuloId);

        // El artículo pudo borrarse entre el dispatch y el procesamiento.
        // Devolver sin más marca el mensaje como manejado (no se reintenta).
        if ($articulo === null) {
            $this->logger->warning('Artículo {id} ya no existe; no se notifica.', ['id' => $mensaje->articuloId]);

            return;
        }

        $suscriptores = $this->suscriptores->todos();
        $this->logger->info('Notificando "{titulo}" a {n} suscriptores...', [
            'titulo' => $articulo->getTitulo(),
            'n' => count($suscriptores),
        ]);

        foreach ($suscriptores as $suscriptor) {
            $email = (new Email())
                ->from('blog@sincrodev.com')
                ->to($suscriptor->getEmail())
                ->subject('Nuevo artículo: '.$articulo->getTitulo())
                ->text(sprintf("Hola,\n\nPublicamos «%s».\n\n— El blog", $articulo->getTitulo()));

            $this->mailer->send($email);

            // El demo usa MAILER_DSN=null:// (no manda nada de verdad), así que
            // simulamos la latencia real de un envío SMTP para que se note la
            // diferencia con hacer esto dentro del request. En producción, esta
            // pausa la pone la red, no un sleep.
            usleep(700_000);

            // Dejamos rastro visible de que el worker hizo el trabajo, y CUÁNDO.
            // La UI lo muestra: las notificaciones aparecen segundos DESPUÉS de
            // que la web respondió "publicado".
            $this->notificaciones->save(
                new Notificacion($articulo->getId(), $suscriptor->getEmail()),
                flush: false,
            );
        }

        $this->notificaciones->flush();
        $this->logger->info('Listo: {n} notificaciones enviadas.', ['n' => count($suscriptores)]);
    }
}
