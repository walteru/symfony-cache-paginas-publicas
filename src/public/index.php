<?php

use App\Kernel;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    // Envolvemos el Kernel con el reverse proxy HttpCache de Symfony para que
    // el demo sea autocontenido (no hace falta Varnish/Nginx-cache).
    //
    //  - Store: misma ruta que el servicio Store de services.yaml, así el
    //    listener de invalidación purga el MISMO cache que sirve la web.
    //  - Esi:   habilita el procesado de los <esi:include> que produce
    //    `render_esi()` en Twig. Sin esto, render_esi() cae a inline y los
    //    fragmentos "frescos" terminarían cacheados dentro de la página.
    //  - debug: hace que HttpCache emita el header X-Symfony-Cache con
    //    miss/store/fresh, que es lo que se mira con `make cache-headers`.
    $store = new Store(dirname(__DIR__).'/var/cache/'.$context['APP_ENV'].'/http_cache');

    return new HttpCache($kernel, $store, new Esi(), [
        'debug' => (bool) $context['APP_DEBUG'],
    ]);
};
