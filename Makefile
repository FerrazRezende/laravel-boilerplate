.DEFAULT_GOAL := help

# npm has to run as the host user, otherwise node_modules ends up owned by root
# and the vite container (which runs as WWW_UID) can no longer write its
# optimization cache, so every dependency 404s at dev time.
RUN_AS := -u $(shell id -u):$(shell id -g)

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

build: ## Build Docker images
	docker compose build

setup: ## Full setup (build, up, install dependencies, migrate)
	docker compose build
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	docker compose exec $(RUN_AS) app npm install
	docker compose exec $(RUN_AS) app npm run build

shell: ## Open a bash shell in the app container
	docker compose exec app bash

artisan: ## Run an artisan command (usage: make artisan args="migrate")
	docker compose exec app php artisan $(args)

migrate: ## Run database migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Fresh migrate with seeds
	docker compose exec app php artisan migrate:fresh --seed

seed: ## Run database seeders
	docker compose exec app php artisan db:seed

horizon: ## Start Horizon (queue supervisor)
	docker compose exec app php artisan horizon

horizon-status: ## Show Horizon's current status
	docker compose exec app php artisan horizon:status

npm-dev: ## Start Vite dev server
	docker compose exec vite npm run dev

npm-build: ## Build frontend assets
	docker compose exec $(RUN_AS) app npm run build

logs: ## Tail logs (optional: service name, e.g. make logs args="app")
	docker compose logs -f $(args)

ps: ## List running containers
	docker compose ps

test: ## Run PHPUnit tests
	docker compose exec app php artisan test

pint: ## Run Laravel Pint code style fixer
	docker compose exec app ./vendor/bin/pint

dev: ## Start dev environment and run dev script
	docker compose up -d
	docker compose exec app bash -c "composer run-script dev"
