# EventOS — developer convenience targets.
# Uses '>' as the recipe prefix (no tab headaches on Windows editors).
.RECIPEPREFIX = >
.DEFAULT_GOAL := help
DC = docker compose

help: ## Show this help
> @grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

build: ## Build all images
> $(DC) build

up: ## Start the core stack (detached)
> $(DC) up -d

up-all: ## Start core + tools (Adminer) + search (Meilisearch)
> $(DC) --profile tools --profile search up -d

down: ## Stop and remove containers
> $(DC) down

down-v: ## Stop and remove containers + volumes (DESTROYS data)
> $(DC) down -v

restart: ## Restart all services
> $(DC) restart

ps: ## Show container status
> $(DC) ps

logs: ## Tail logs for all services
> $(DC) logs -f --tail=100

logs-api: ## Tail API logs
> $(DC) logs -f --tail=100 api nginx

shell: ## Bash into the api container
> $(DC) exec api bash

tinker: ## Laravel tinker REPL
> $(DC) exec api php artisan tinker

migrate: ## Run migrations (migrator role)
> $(DC) exec api php artisan migrate --force --database=pgsql_admin

migrate-status: ## Show migration status
> $(DC) exec api php artisan migrate:status --database=pgsql_admin

fresh: ## Drop everything, re-migrate and seed (migrator role)
> $(DC) exec api php artisan migrate:fresh --seed --force --database=pgsql_admin

seed: ## Run database seeders (migrator role)
> $(DC) exec api php artisan db:seed --force --database=pgsql_admin

key: ## Generate APP_KEY
> $(DC) exec api php artisan key:generate

test: ## Run the API test suite
> $(DC) exec api php artisan test

psql: ## psql as the superuser
> $(DC) exec postgres psql -U postgres -d eventos

psql-app: ## psql as eventos_app (to manually test RLS)
> $(DC) exec postgres psql -U eventos_app -d eventos

redis-cli: ## redis-cli
> $(DC) exec redis redis-cli

health: ## Curl the API health endpoint
> curl -s http://localhost:8080/api/v1/health || true
