.PHONY: up down wait test coverage help

.DEFAULT_GOAL := help

## mysql config
MYSQL_VERSION ?= 8.0
MYSQL_CONTAINER_IMAGE := mysql:${MYSQL_VERSION}
MYSQL_CONTAINER_NAME := laravel-query-wizard-mysql
MYSQL_HOST_PORT := 23306
MYSQL_DATABASE := test
MYSQL_USER := test
MYSQL_PASSWORD := test

up: ## Start containers
	@printf "\033[93m→ Starting ${MYSQL_CONTAINER_NAME} container\033[0m\n"
	@docker run --rm -d \
		--name ${MYSQL_CONTAINER_NAME} \
		-p ${MYSQL_HOST_PORT}:3306 \
		-e MYSQL_RANDOM_ROOT_PASSWORD=yes \
		-e MYSQL_DATABASE=${MYSQL_DATABASE} \
		-e MYSQL_USER=${MYSQL_USER} \
		-e MYSQL_PASSWORD=${MYSQL_PASSWORD} \
		${MYSQL_CONTAINER_IMAGE} \
		--default-authentication-plugin=mysql_native_password
	@printf "\033[92m✔︎ ${MYSQL_CONTAINER_NAME} is started\033[0m\n"

down: ## Stop containers
	@printf "\033[93m→ Stopping containers\033[0m\n"
	@docker stop ${MYSQL_CONTAINER_NAME}
	@printf "\033[92m✔︎ Containers are stopped\033[0m\n"


wait: ## Wait until containers are ready
	@printf "\033[93m→ Waiting for ${MYSQL_CONTAINER_NAME} container\033[0m\n"
	@until docker exec ${MYSQL_CONTAINER_NAME} mysqladmin -u ${MYSQL_USER} -p${MYSQL_PASSWORD} -h 127.0.0.1 ping; do \
		printf "\033[91m✘ ${MYSQL_CONTAINER_NAME} is not ready, waiting...\033[0m\n"; \
		sleep 5; \
	done
	@printf "\033[92m✔︎ ${MYSQL_CONTAINER_NAME} is ready\033[0m\n"

test: ## Run tests
	@printf "\033[93m→ Running tests\033[0m\n"
	@vendor/bin/phpunit --testdox
	@printf "\n\033[92m✔︎ Tests are completed\033[0m\n"

coverage: ## Run tests and generate the code coverage report
	@printf "\033[93m→ Running tests and generating the code coverage report\033[0m\n"
	@XDEBUG_MODE=coverage vendor/bin/phpunit --testdox --coverage-text
	@printf "\n\033[92m✔︎ Tests are completed and the report is generated\033[0m\n"

help: ## Show help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
