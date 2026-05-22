<?php

namespace App\Tests\Workflow;

use App\Entity\Articulo;
use App\Message\NotificarSuscriptores;
use App\Workflow\PublicacionSubscriber;
use App\Workflow\RolActual;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class PublicacionSubscriberTest extends TestCase
{
    private function rol(string $valor): RolActual
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack();
        $stack->push($request);
        $rol = new RolActual($stack);
        $rol->set($valor);

        return $rol;
    }

    private function bus(): MessageBusInterface
    {
        // Stub: solo necesita devolver un Envelope; no verificamos llamadas acá.
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(fn ($m) => new Envelope($m));

        return $bus;
    }

    private function sub(string $rol): PublicacionSubscriber
    {
        return new PublicacionSubscriber($this->rol($rol), $this->bus());
    }

    private function guard(Articulo $articulo, string $transicion, string $from, string $to): GuardEvent
    {
        return new GuardEvent($articulo, new Marking([$from => 1]), new Transition($transicion, $from, $to));
    }

    public function testNoSeMandaARevisionUnArticuloCorto(): void
    {
        $articulo = (new Articulo())->setContenido('muy corto');

        $event = $this->guard($articulo, 'enviar_a_revision', 'borrador', 'en_revision');
        $this->sub(RolActual::AUTOR)->guardEnviarARevision($event);

        $this->assertTrue($event->isBlocked(), 'Un artículo de menos de 50 caracteres no debería pasar a revisión');
    }

    public function testArticuloLargoSiPasaARevision(): void
    {
        $articulo = (new Articulo())->setContenido(str_repeat('contenido suficiente ', 5));

        $event = $this->guard($articulo, 'enviar_a_revision', 'borrador', 'en_revision');
        $this->sub(RolActual::AUTOR)->guardEnviarARevision($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testUnAutorNoPuedeAprobar(): void
    {
        $event = $this->guard(new Articulo(), 'aprobar', 'en_revision', 'aprobado');
        $this->sub(RolActual::AUTOR)->guardSoloEditor($event);

        $this->assertTrue($event->isBlocked());
    }

    public function testUnEditorSiPuedeAprobar(): void
    {
        $event = $this->guard(new Articulo(), 'aprobar', 'en_revision', 'aprobado');
        $this->sub(RolActual::EDITOR)->guardSoloEditor($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testAlPublicarSeSellaLaFecha(): void
    {
        $articulo = new Articulo();
        // En el flujo real el artículo ya está persistido (tiene id) al publicar.
        (new \ReflectionProperty(Articulo::class, 'id'))->setValue($articulo, 1);
        $this->assertNull($articulo->getPublicadoEl());

        $event = new EnteredEvent($articulo, new Marking(['publicado' => 1]), new Transition('publicar', 'aprobado', 'publicado'));
        $this->sub(RolActual::EDITOR)->alPublicar($event);

        $this->assertNotNull($articulo->getPublicadoEl());
    }

    /** El efecto clave del post: publicar DESPACHA el mensaje (no manda mails acá). */
    public function testAlPublicarSeDespachaLaNotificacion(): void
    {
        $articulo = (new Articulo())->setEstado('aprobado');
        // Forzamos un id como tendría una entidad ya persistida.
        $ref = new \ReflectionProperty(Articulo::class, 'id');
        $ref->setValue($articulo, 42);

        $despachados = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($mensaje) use (&$despachados) {
                $despachados[] = $mensaje;

                return new Envelope($mensaje);
            });

        $sub = new PublicacionSubscriber($this->rol(RolActual::EDITOR), $bus);
        $event = new EnteredEvent($articulo, new Marking(['publicado' => 1]), new Transition('publicar', 'aprobado', 'publicado'));
        $sub->alPublicar($event);

        $this->assertCount(1, $despachados);
        $this->assertInstanceOf(NotificarSuscriptores::class, $despachados[0]);
        $this->assertSame(42, $despachados[0]->articuloId);
    }
}
