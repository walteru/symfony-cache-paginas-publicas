<?php

namespace App\Tests\MessageHandler;

use App\Entity\Articulo;
use App\Entity\Notificacion;
use App\Entity\Suscriptor;
use App\Message\NotificarSuscriptores;
use App\MessageHandler\NotificarSuscriptoresHandler;
use App\Repository\ArticuloRepository;
use App\Repository\NotificacionRepository;
use App\Repository\SuscriptorRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

class NotificarSuscriptoresHandlerTest extends TestCase
{
    public function testNotificaACadaSuscriptorYDejaRegistro(): void
    {
        $articulo = (new Articulo())->setTitulo('Nuevo post');
        (new \ReflectionProperty(Articulo::class, 'id'))->setValue($articulo, 7);

        $articulos = $this->createStub(ArticuloRepository::class);
        $articulos->method('find')->willReturn($articulo);

        $suscriptores = $this->createStub(SuscriptorRepository::class);
        $suscriptores->method('todos')->willReturn([new Suscriptor('a@x.com'), new Suscriptor('b@x.com')]);

        // Un mail por suscriptor.
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->exactly(2))->method('send')->with($this->isInstanceOf(RawMessage::class));

        // Una Notificacion persistida por suscriptor, y un único flush al final.
        $guardadas = [];
        $notificaciones = $this->createMock(NotificacionRepository::class);
        $notificaciones->method('save')->willReturnCallback(function (Notificacion $n) use (&$guardadas) {
            $guardadas[] = $n;
        });
        $notificaciones->expects($this->once())->method('flush');

        $handler = new NotificarSuscriptoresHandler($articulos, $suscriptores, $notificaciones, $mailer, new NullLogger());
        $handler(new NotificarSuscriptores(7));

        $this->assertCount(2, $guardadas);
        $this->assertSame(['a@x.com', 'b@x.com'], array_map(fn (Notificacion $n) => $n->getEmail(), $guardadas));
        $this->assertSame(7, $guardadas[0]->getArticuloId());
    }

    public function testSiElArticuloNoExisteNoFalla(): void
    {
        $articulos = $this->createStub(ArticuloRepository::class);
        $articulos->method('find')->willReturn(null);

        $suscriptores = $this->createMock(SuscriptorRepository::class);
        $suscriptores->expects($this->never())->method('todos');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $notificaciones = $this->createStub(NotificacionRepository::class);

        $handler = new NotificarSuscriptoresHandler($articulos, $suscriptores, $notificaciones, $mailer, new NullLogger());
        $handler(new NotificarSuscriptores(999));

        $this->addToAssertionCount(1); // llegó hasta acá sin excepción
    }
}
