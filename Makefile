PHP     = php
PEST    = $(PHP) vendor/bin/pest
ARTISAN = $(PHP) artisan

# ---------------------------------------------------------------------------
# Testing
# ---------------------------------------------------------------------------

test:                         ## Run all tests locally (requires pdo_sqlite)
	$(ARTISAN) test

test-unit:                    ## Run Unit tests only
	$(ARTISAN) test --testsuite=Unit

test-feature:                 ## Run Feature tests only
	$(ARTISAN) test --testsuite=Feature

test-parallel:                ## Run all tests in parallel
	$(ARTISAN) test --parallel

test-filter:                  ## Run tests matching a name  →  make test-filter q="state machine"
	$(ARTISAN) test --filter="$(q)"

test-docker:                  ## Run all tests inside Docker (no local extensions needed)
	docker run --rm \
		-v $(PWD):/app \
		-w /app \
		-e APP_ENV=testing \
		-e APP_KEY=base64:eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHg= \
		-e DB_CONNECTION=sqlite \
		-e DB_DATABASE=":memory:" \
		-e CACHE_STORE=array \
		-e QUEUE_CONNECTION=sync \
		-e SESSION_DRIVER=array \
		php:8.4-cli sh -c "php -m | grep -q bcmath || docker-php-ext-install bcmath >/dev/null 2>&1; php artisan test"

# ---------------------------------------------------------------------------
# Shortcuts
# ---------------------------------------------------------------------------

.PHONY: test test-unit test-feature test-parallel test-filter test-docker
