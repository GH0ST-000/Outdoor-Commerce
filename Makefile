# Copy from examples only when missing
setup-env:
	@test -f .env || cp .env.example .env
	@test -f backend/.env || cp backend/.env.example backend/.env
	@test -f frontend/.env || cp frontend/.env.example frontend/.env
	@echo "Environment files ready."

# First-time and repeatable local bootstrap
setup: setup-env
	@echo "Building containers..."
	docker compose build
	@echo "Starting infrastructure..."
	docker compose up -d mysql redis meilisearch mailpit
	@echo "Waiting for infrastructure health..."
	@./scripts/wait-for-healthy.sh mysql redis meilisearch mailpit
	@echo "Starting application services..."
	docker compose up -d backend frontend
	@./scripts/wait-for-healthy.sh backend frontend
	@$(MAKE) key-generate
	@$(MAKE) migrate
	@$(MAKE) urls
	@echo "Setup complete."

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f --tail=200

ps:
	docker compose ps

backend-shell:
	docker compose exec backend sh

frontend-shell:
	docker compose exec frontend sh

key-generate:
	@docker compose exec -T backend php artisan key:generate --show >/dev/null 2>&1 || true
	@docker compose exec -T backend sh -c 'grep -q "^APP_KEY=base64:" .env || php artisan key:generate --force'

migrate:
	docker compose exec -T backend php artisan migrate --force

# Host-side tests (CI parity; does not require Docker app containers)
test:
	@$(MAKE) test-backend
	@$(MAKE) test-frontend

test-backend:
	cd backend && php -d display_errors=0 -d error_reporting=22527 /usr/local/bin/composer test

test-frontend:
	cd frontend && npm run test:run

# Host-side quality (CI parity)
quality:
	@$(MAKE) quality-backend
	@$(MAKE) quality-frontend

quality-backend:
	cd backend && php -d display_errors=0 -d error_reporting=22527 /usr/local/bin/composer quality

quality-frontend:
	cd frontend && npm run format:check && npm run lint -- --max-warnings=0 && npm run typecheck && npm run test:run

# Full local CI equivalent (no GitHub-hosted runners / no image push)
ci:
	@chmod +x scripts/ci/validate-repository.sh
	./scripts/ci/validate-repository.sh
	cd backend && php -d display_errors=0 -d error_reporting=22527 /usr/local/bin/composer ci
	cd frontend && npm run format:check
	cd frontend && npm run lint -- --max-warnings=0
	cd frontend && npm run typecheck
	cd frontend && npm run test:run
	cd frontend && npm run build
	docker compose config -q
	DOCKER_BUILDKIT=1 docker build -f docker/backend/Dockerfile -t outdoor-commerce-backend:ci .
	DOCKER_BUILDKIT=1 docker build -f docker/frontend/Dockerfile -t outdoor-commerce-frontend:ci .
	cd backend && php -d display_errors=0 -d error_reporting=22527 /usr/local/bin/composer audit --no-interaction
	cd frontend && npm audit --audit-level=high
	@echo "Local CI equivalent finished successfully."

# Optional: run quality inside Compose app containers
quality-docker:
	docker compose exec -T backend composer quality
	docker compose exec -T frontend npm run quality

test-docker:
	docker compose exec -T backend composer test
	docker compose exec -T frontend npm run test:run

# Safe clean: stop containers and remove anonymous leftovers, keep named volumes
clean:
	docker compose down --remove-orphans
	@echo "Containers stopped. Named volumes were preserved."
	@echo "To delete database and other persistent volumes, run: make clean-volumes"

# Explicit destructive command for persistent data
clean-volumes:
	@echo "WARNING: This deletes MySQL, Redis, and Meilisearch named volumes."
	@echo "Run: docker compose down -v"

urls:
	@echo ""
	@echo "Local URLs"
	@echo "  Frontend:        http://localhost:3000"
	@echo "  Backend API:     http://localhost:8000"
	@echo "  Backend health:  http://localhost:8000/api/health"
	@echo "  Frontend health: http://localhost:3000/api/health"
	@echo "  Mailpit UI:      http://localhost:8025"
	@echo "  Meilisearch:     http://localhost:7700"
	@echo "  MySQL:           localhost:3306"
	@echo "  Redis:           localhost:6379"
	@echo ""

.PHONY: setup setup-env up down restart logs ps backend-shell frontend-shell key-generate migrate test test-backend test-frontend quality quality-backend quality-frontend ci quality-docker test-docker clean clean-volumes urls
