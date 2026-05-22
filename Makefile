DOCKER_APP = cache-paginas-app

.DEFAULT_GOAL := help

help: ## Muestra esta ayuda
	@echo 'uso: make [target]'
	@echo
	@egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'

start: ## Levanta los contenedores (web + worker + redis)
	docker compose up -d --remove-orphans
	@echo "App: http://localhost:8094"

stop: ## Detiene los contenedores
	docker compose stop

down: ## Baja contenedores y red
	docker compose down --remove-orphans

build: ## Construye las imágenes
	docker compose build

rebuild: ## Reconstruye desde cero y levanta
	docker compose down --remove-orphans && docker compose build --no-cache && docker compose up -d

logs: ## Logs en vivo (todos los servicios)
	docker compose logs -f

worker-logs: ## Logs SOLO del worker (mirá cómo procesa la cola en vivo)
	docker compose logs -f worker

sh: ## Shell dentro del contenedor de la app
	docker compose exec app bash

composer: ## Ejecuta composer dentro del contenedor (ej: make composer c="require foo")
	docker compose exec app composer $(c)

console: ## Ejecuta bin/console (ej: make console c="cache:clear")
	docker compose exec -u www-data app php bin/console $(c)

migrate: ## Crea la base SQLite y aplica el esquema
	docker compose exec -u www-data app php bin/console doctrine:schema:update --force --complete

fixtures: ## Carga artículos y suscriptores de ejemplo
	docker compose exec -u www-data app php bin/console app:cargar-ejemplos

failed: ## Muestra los mensajes que agotaron sus reintentos (failure transport)
	docker compose exec -u www-data app php bin/console messenger:failed:show

failed-retry: ## Reintenta a mano los mensajes fallidos
	docker compose exec -u www-data app php bin/console messenger:failed:retry

test: ## Corre la suite de tests (PHPUnit)
	docker compose exec app php bin/phpunit

cache-headers: ## Pide /p/articulos (la pública cacheable) varias veces y muestra los headers (1.ª miss/store, siguientes fresh)
	@for i in 1 2 3; do \
		echo "--- request $$i ---"; \
		curl -s -D - -o /dev/null http://localhost:8094/p/articulos | grep -E "X-Symfony-Cache|Cache-Control|Age|^HTTP/"; \
	done
