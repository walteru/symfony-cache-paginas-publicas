# Cache HTTP de páginas públicas con Symfony

Demo **autocontenida** (clone & run con Docker) del [componente HttpCache + ESI](https://symfony.com/doc/current/http_cache.html) de Symfony, aplicado a un caso real: el **listado y la ficha pública** del blog editorial — cacheados con `s-maxage`, con un **fragmento ESI** (contador de vistas) que se renderiza fresco en cada hit y con **purga automática** cuando el contenido cambia.

Es la **tercera parte** de una serie. Las dos primeras:

- [#1 Workflow](https://github.com/walteru/symfony-workflow-flujo-editorial): cuándo un artículo puede pasar a publicado.
- [#2 Messenger](https://github.com/walteru/symfony-messenger-tareas-async): sacar el envío de mails fuera del request.

En este demo se ve la composición: cuando un artículo entra a `publicado`, el mismo evento del Workflow termina disparando **tres efectos** en paralelo:

```
                        ┌──── (Workflow #1)  sella la fecha publicadoEl
entered.publicado  ─────┼──── (Messenger #2) encola la notificación a suscriptores
                        └──── (Cache    #3) purga /p/articulos y /p/articulos/{id}
```

El controller no sabe nada de esto.

## Qué muestra el demo

- **Cache de páginas enteras** (`Cache-Control: public, s-maxage=60`) en las dos vistas públicas.
- **Fragmento ESI** para el contador de vistas: la página exterior sigue siendo *cache hit*, pero el contador se recalcula en cada visita (`<esi:include>` resuelto por el reverse proxy de Symfony, sin Varnish).
- **Invalidación quirúrgica** mediante `Store::purge()` desde un listener del Workflow.
- **Separación admin / pública**: el backoffice editorial (formularios, CSRF, rol en sesión) vive en `/articulos/...` y **no se cachea**; lo público vive en `/p/articulos/...` y sí.

## Requisitos

Solo **Docker** y **Docker Compose**. No necesitás PHP ni Composer en el host: el primer arranque instala las dependencias dentro del contenedor.

## Cómo correrlo

```bash
make start      # construye y levanta web + worker + redis (http://localhost:8094)
make migrate    # crea la base SQLite y el esquema
make fixtures   # carga artículos y suscriptores de ejemplo
```

Abrí <http://localhost:8094> (te lleva a la admin). Desde ahí, publicá el artículo *«Sacá el trabajo lento del request»*. Después andá a <http://localhost:8094/p/articulos> y mirá la vista pública.

## Mini-medición (el efecto, con headers)

```bash
make cache-headers
```

```
--- request 1 ---
Cache-Control: public, s-maxage=60
X-Symfony-Cache: GET /p/articulos: miss, store
--- request 2 ---
Cache-Control: public, s-maxage=60
X-Symfony-Cache: GET /p/articulos: fresh
--- request 3 ---
Cache-Control: public, s-maxage=60
X-Symfony-Cache: GET /p/articulos: fresh
```

La primera respuesta toca el backend (`miss, store`); las siguientes las sirve directamente el reverse proxy (`fresh`).

En la ficha (`/p/articulos/{id}`), refrescá varias veces y mirá el contador de vistas: la página exterior es `fresh`, pero el número sube en cada hit (el `_fragment` interno es `miss` cada vez):

```
X-Symfony-Cache: GET /p/articulos/3: fresh; GET /_fragment?...vistas: miss
```

## Composición con #1 y #2 (la gracia de la serie)

Cuando hacés clic en *Publicar* en la admin:

1. El Workflow valida la transición (`#1`) y aplica el estado.
2. En el evento `entered.publicado`:
   - se sella `publicadoEl` (`#1`);
   - se despacha `NotificarSuscriptores` que el worker procesa (`#2`);
   - el listener de cache purga `/p/articulos` y `/p/articulos/{id}` (este demo).
3. La siguiente request a `/p/articulos` ve `miss, store` y muestra el nuevo artículo. No hay que invalidar nada a mano.

## Cómo está armado

| Pieza | Archivo |
|---|---|
| Front controller con `HttpCache` (+ `Esi`) | `src/public/index.php` |
| ESI + fragments habilitados | `src/config/packages/framework.yaml` |
| Servicio `Store` (misma ruta que el front) | `src/config/services.yaml` |
| Controller público (rutas cacheables) | `src/src/Controller/PublicoController.php` |
| Listener de invalidación | `src/src/Cache/CacheInvalidationSubscriber.php` |
| Templates públicos | `src/templates/publico/` |
| Tests del listener | `src/tests/Cache/CacheInvalidationSubscriberTest.php` |

## Cuándo usar / cuándo NO

**Usar** para páginas:
- públicas (sin contenido específico del usuario),
- leídas muchas más veces de las que se modifican,
- que toleran segundos/minutos de antigüedad (blog, catálogos, documentación, landings, listados).

**No usar** si:
- la página depende del usuario (cookies de sesión, CSRF, vistas personalizadas) — antes hay que aislar la parte pública o irse a ESI;
- los datos tienen que estar al segundo (precios en vivo, saldos, contadores duros);
- los permisos son complejos por usuario;
- **todavía no medís un problema** — cachear sin medir es agregar complejidad por las dudas.

## Stack

- Symfony 6.4 (PHP 8.3) · HttpCache + ESI (sin Varnish) · Doctrine ORM con **SQLite**
- Messenger + Redis (heredado de #2, para mantener la composición de la serie)
- Twig · Apache en el contenedor web

## Licencia

MIT — ver [LICENSE](LICENSE).
