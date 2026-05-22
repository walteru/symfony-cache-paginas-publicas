<?php

namespace App\Tests\Cache;

use App\Cache\CacheInvalidationSubscriber;
use App\Entity\Articulo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class CacheInvalidationSubscriberTest extends TestCase
{
    public function testPurgaListadoYDetalleAlEntrarAPublicado(): void
    {
        // Articulo persistido con id=42 y un request en curso (simula la admin
        // POST /articulos/42/transicion/publicar a http://localhost:8094).
        $articulo = (new Articulo())->setTitulo('Un post');
        (new \ReflectionProperty(Articulo::class, 'id'))->setValue($articulo, 42);

        $request = Request::create('http://localhost:8094/articulos/42/transicion/publicar', 'POST');
        $stack = new RequestStack();
        $stack->push($request);

        $purgadas = [];
        $store = $this->createMock(Store::class);
        $store->expects($this->exactly(2))
            ->method('purge')
            ->willReturnCallback(function (string $url) use (&$purgadas) {
                $purgadas[] = $url;

                return true;
            });

        $sub = new CacheInvalidationSubscriber($store, $stack);
        $event = new EnteredEvent($articulo, new Marking(['publicado' => 1]), new Transition('publicar', 'aprobado', 'publicado'));
        $sub->alPublicar($event);

        sort($purgadas);
        $this->assertSame([
            'http://localhost:8094/p/articulos',
            'http://localhost:8094/p/articulos/42',
        ], $purgadas);
    }

    public function testNoExplotaSinRequestEnContextoCli(): void
    {
        // Caso CLI (ej: una transición desde un comando). No hay request actual
        // y no debe lanzar excepción ni reventar al pedirle al RequestStack.
        $articulo = (new Articulo());
        (new \ReflectionProperty(Articulo::class, 'id'))->setValue($articulo, 7);

        $store = $this->createStub(Store::class);
        $store->method('purge')->willReturn(true);

        $sub = new CacheInvalidationSubscriber($store, new RequestStack());
        $event = new EnteredEvent($articulo, new Marking(['publicado' => 1]), new Transition('publicar', 'aprobado', 'publicado'));

        $sub->alPublicar($event); // no debería tirar
        $this->addToAssertionCount(1);
    }
}
