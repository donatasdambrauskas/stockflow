.PHONY: init start stop down restart logs shell composer symfony db-migrate db-seed db-reset test

DOCKER_COMPOSE := docker compose
PHP_SERVICE := php

init: .env
	$(DOCKER_COMPOSE) build
	$(DOCKER_COMPOSE) up -d --wait database
	@if [ ! -f composer.json ]; then \
		echo "Creating Symfony 8.1 project..."; \
		cp README.md /tmp/stockflow-readme.bak; \
		$(DOCKER_COMPOSE) run --rm --no-deps --user www-data $(PHP_SERVICE) sh -c 'composer create-project symfony/skeleton:"8.1.*" .tmp-symfony --no-interaction --remove-vcs && cp -a .tmp-symfony/. /var/www/html/ && rm -rf .tmp-symfony'; \
		cp /tmp/stockflow-readme.bak README.md; \
		rm -f /tmp/stockflow-readme.bak; \
	fi
	$(DOCKER_COMPOSE) run --rm --no-deps --user www-data $(PHP_SERVICE) composer install --no-interaction
	@echo ""
	@echo "Init complete. Run 'make start' to start all services."

.env:
	@test -f .env || cp .env.dist .env

start: .env
	$(DOCKER_COMPOSE) up -d

stop:
	$(DOCKER_COMPOSE) stop

down:
	$(DOCKER_COMPOSE) down

restart: stop start

logs:
	$(DOCKER_COMPOSE) logs -f

shell:
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) sh

composer:
	$(DOCKER_COMPOSE) run --rm --user www-data $(PHP_SERVICE) composer $(filter-out $@,$(MAKECMDGOALS))

symfony:
	$(DOCKER_COMPOSE) run --rm --user www-data $(PHP_SERVICE) php bin/console $(filter-out $@,$(MAKECMDGOALS))

db-migrate:
	$(DOCKER_COMPOSE) run --rm --user www-data $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

db-seed:
	$(DOCKER_COMPOSE) exec -T database mariadb -ustockflow -pstockflow stockflow < data/seed.sql

db-reset: .env
	$(DOCKER_COMPOSE) run --rm --user www-data $(PHP_SERVICE) php bin/console doctrine:schema:drop --force --full-database
	$(MAKE) db-migrate
	$(MAKE) db-seed

test:
	$(DOCKER_COMPOSE) run --rm --user www-data $(PHP_SERVICE) php bin/phpunit

%:
	@:
