.PHONY: help up down logs shell shell-nginx shell-mysql install migrate seed test build

.DEFAULT_GOAL := help

help: ## Show this help message
	@echo "AgencySync - Developer Commands"
	@echo ""
	@echo "Usage:"
	@echo "  make [target]"
	@echo ""
	@echo "Available targets:"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

up: ## Start all containers in detached mode
	docker compose up -d
	@echo "Containers started. Access app at http://localhost"

down: ## Stop all containers
	docker compose down
	@echo "Containers stopped."

logs: ## View logs from all containers (follow mode)
	docker compose logs -f

shell: ## Shell into app container
	docker compose exec app bash

shell-nginx: ## Shell into nginx container (for debugging)
	docker compose exec nginx sh

shell-mysql: ## Connect to MySQL CLI
	docker compose exec mysql mysql -u$(DB_USERNAME) -p$(DB_PASSWORD) $(DB_DATABASE)

shell-redis: ## Connect to Redis CLI
	docker compose exec redis redis-cli

install: ## Run composer install in app container
	docker compose exec app composer install

npm-install: ## Run npm install in app container
	docker compose exec app npm install

npm-dev: ## Run npm dev in app container
	docker compose exec app npm run dev

npm-build: ## Run npm build in app container
	docker compose exec app npm run build

migrate: ## Run database migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Drop all tables and re-run migrations
	docker compose exec app php artisan migrate:fresh

migrate-rollback: ## Rollback the last database migration
	docker compose exec app php artisan migrate:rollback

seed: ## Seed database
	docker compose exec app php artisan db:seed

test: ## Run PHPUnit tests
	docker compose exec app php artisan test

test-coverage: ## Run tests with coverage report
	docker compose exec app php artisan test --coverage

optimize: ## Optimize the application for production
	docker compose exec app php artisan optimize

clear-cache: ## Clear all application caches
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear

key-generate: ## Generate application key
	docker compose exec app php artisan key:generate

build: ## First-time setup (build + install + setup)
	docker compose build
	docker compose run --rm app composer install
	@if [ ! -f .env ]; then \
		cp .env.docker .env; \
		echo "Created .env from .env.docker"; \
	fi
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	@echo ""
	@echo "Build complete! Run 'make up' to start containers."

rebuild: ## Rebuild and restart containers
	docker compose down
	docker compose build --no-cache
	docker compose up -d
	@echo "Rebuild complete. Containers started."

ps: ## Show running containers
	docker compose ps

restart: ## Restart all containers
	docker compose restart
	@echo "Containers restarted."

clean: ## Remove all containers, volumes, and images
	docker compose down -v --remove-orphans
	@echo "All containers, volumes, and orphaned containers removed."
