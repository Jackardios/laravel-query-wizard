.PHONY: test unit-test feature-test coverage lint format-check format static-analysis ci test-matrix test-full-matrix install update help build-images

.DEFAULT_GOAL := help

# Colors
GREEN  := \033[92m
YELLOW := \033[93m
RED    := \033[91m
CYAN   := \033[36m
RESET  := \033[0m

# Supported versions for matrix testing
PHP_VERSIONS := 8.1 8.2 8.3 8.4
LARAVEL_VERSIONS := 10 11 12

# Docker image for matrix testing
DOCKER_IMAGE_PREFIX := laravel-query-wizard-php
COMPOSER_CACHE_VOLUME := laravel-query-wizard-composer-cache

# Extra arguments for phpunit (use: make test ARGS="--filter=testName")
ARGS ?=

##@ Testing

test: ## Run all tests (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running all tests$(RESET)\n"
	@vendor/bin/phpunit --testdox --colors=always $(ARGS)
	@printf "$(GREEN)✔ Tests completed$(RESET)\n"

unit-test: ## Run unit tests only (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running unit tests$(RESET)\n"
	@vendor/bin/phpunit --testsuite=Unit --testdox --colors=always $(ARGS)
	@printf "$(GREEN)✔ Unit tests completed$(RESET)\n"

feature-test: ## Run feature tests (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running feature tests$(RESET)\n"
	@vendor/bin/phpunit --testsuite=Feature --testdox --colors=always $(ARGS)
	@printf "$(GREEN)✔ Feature tests completed$(RESET)\n"

coverage: ## Run tests with coverage (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running tests with coverage$(RESET)\n"
	@XDEBUG_MODE=coverage vendor/bin/phpunit --testdox --coverage-text --colors=always $(ARGS)
	@printf "$(GREEN)✔ Coverage report generated$(RESET)\n"

test-matrix: build-images ## Run tests on all PHP/Laravel versions via Docker
	@printf "$(CYAN)════════════════════════════════════════════════════════════$(RESET)\n"
	@printf "$(CYAN)  Running full test matrix (PHP × Laravel)$(RESET)\n"
	@printf "$(CYAN)════════════════════════════════════════════════════════════$(RESET)\n"
	@passed=0; failed=0; skipped=0; \
	for php_version in $(PHP_VERSIONS); do \
		for laravel_version in $(LARAVEL_VERSIONS); do \
			if [ "$$php_version" = "8.1" ] && [ "$$laravel_version" != "10" ]; then \
				printf "$(YELLOW)⊘ PHP $$php_version / Laravel $$laravel_version - skipped (Laravel $$laravel_version requires PHP 8.2+)$(RESET)\n"; \
				skipped=$$((skipped + 1)); \
				continue; \
			fi; \
			printf "\n$(CYAN)▶ PHP $$php_version / Laravel $$laravel_version$(RESET)\n"; \
			case $$laravel_version in \
				10) testbench_version=8 ;; \
				11) testbench_version=9 ;; \
				12) testbench_version=10 ;; \
			esac; \
			if docker run --rm \
				-v "$$(pwd):/src:ro" \
				-v $(COMPOSER_CACHE_VOLUME):/root/.composer/cache \
				-w /app \
				$(DOCKER_IMAGE_PREFIX):$$php_version sh -c "\
					cp -r /src/. /app/ && \
					composer update \
						--with='orchestra/testbench:^$$testbench_version.0' \
						--prefer-dist --no-interaction --no-progress && \
					vendor/bin/phpunit --colors=always \
				"; then \
				printf "$(GREEN)✔ PHP $$php_version / Laravel $$laravel_version passed$(RESET)\n"; \
				passed=$$((passed + 1)); \
			else \
				printf "$(RED)✘ PHP $$php_version / Laravel $$laravel_version failed$(RESET)\n"; \
				failed=$$((failed + 1)); \
			fi; \
		done; \
	done; \
	printf "\n$(CYAN)════════════════════════════════════════════════════════════$(RESET)\n"; \
	printf "$(CYAN)  Results: $(GREEN)$$passed passed$(CYAN), $(RED)$$failed failed$(CYAN), $(YELLOW)$$skipped skipped$(RESET)\n"; \
	printf "$(CYAN)════════════════════════════════════════════════════════════$(RESET)\n"; \
	[ $$failed -eq 0 ]

##@ Code Quality

lint: format-check static-analysis ## Quick lint check (no tests)

format-check: ## Check code style (dry-run)
	@printf "$(YELLOW)→ Checking code style$(RESET)\n"
	@vendor/bin/pint --test
	@printf "$(GREEN)✔ Code style OK$(RESET)\n"

format: ## Fix code style
	@printf "$(YELLOW)→ Fixing code style$(RESET)\n"
	@vendor/bin/pint
	@printf "$(GREEN)✔ Code style fixed$(RESET)\n"

static-analysis: ## Run PHPStan static analysis
	@printf "$(YELLOW)→ Running static analysis$(RESET)\n"
	@vendor/bin/phpstan analyse --memory-limit=512M
	@printf "$(GREEN)✔ Static analysis passed$(RESET)\n"

##@ CI

ci: format-check static-analysis test ## Run all CI checks
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"
	@printf "$(GREEN)  All CI checks passed!$(RESET)\n"
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"

ci-full: format-check static-analysis test-matrix ## Run full CI with complete matrix (PHP × Laravel)
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"
	@printf "$(GREEN)  Full CI passed!$(RESET)\n"
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"

##@ Docker

build-images: ## Build Docker images for matrix testing
	@printf "$(YELLOW)→ Building Docker images for matrix testing$(RESET)\n"
	@for php_version in $(PHP_VERSIONS); do \
		printf "$(CYAN)▶ Building $(DOCKER_IMAGE_PREFIX):$$php_version$(RESET)\n"; \
		docker build -q -t $(DOCKER_IMAGE_PREFIX):$$php_version \
			--build-arg PHP_VERSION=$$php_version \
			docker/; \
	done
	@docker volume create $(COMPOSER_CACHE_VOLUME) >/dev/null 2>&1 || true
	@printf "$(GREEN)✔ Images built$(RESET)\n"

##@ Development

install: ## Install dependencies
	@composer install

update: ## Update dependencies
	@composer update

##@ Help

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\n$(CYAN)Usage:$(RESET)\n  make $(YELLOW)<target>$(RESET)\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(YELLOW)%-18s$(RESET) %s\n", $$1, $$2 } /^##@/ { printf "\n$(CYAN)%s$(RESET)\n", substr($$0, 5) }' $(MAKEFILE_LIST)
