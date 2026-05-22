#!/bin/sh
set -e

# El código vive en un volumen montado desde el host. var/ (cache, logs y la
# base SQLite) lo tiene que escribir Apache (www-data), así que le damos la
# propiedad en cada arranque.
mkdir -p var
chown -R www-data:www-data var

# --- Contenedor worker -------------------------------------------------------
# Si se pasa un comando (docker-compose se lo da al worker), esperamos a que la
# web haya instalado las dependencias y lo ejecutamos. NO instala él para no
# pisarse con la web escribiendo el mismo vendor/.
if [ "$#" -gt 0 ]; then
    until [ -f vendor/autoload.php ]; do
        echo "[worker] esperando a que la web instale dependencias..."
        sleep 2
    done
    exec "$@"
fi

# --- Contenedor web ----------------------------------------------------------
# Primer arranque tras el clone: vendor/ no existe, lo instalamos. Esto hace que
# "clone & run" funcione sin tener PHP ni Composer en el host.
if [ ! -f vendor/autoload.php ]; then
    echo "[web] instalando dependencias (composer install)..."
    composer install --no-interaction --prefer-dist --no-progress
    chown -R www-data:www-data var
fi

exec apache2-foreground
