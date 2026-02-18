.DEFAULT_GOAL := help
UTILS := ./utils
DEMO  := ./utils php demo/demo

##
## ── Docker ───────────────────────────────────────────────────────────────────
##

up: ## Start Docker containers
	$(UTILS) up

down: ## Stop Docker containers
	$(UTILS) down

restart: ## Restart Docker containers
	$(UTILS) restart

build: ## Build Docker containers
	$(UTILS) build

rebuild: ## Rebuild Docker containers from scratch (no cache)
	$(UTILS) rebuild

status: ## Check if Docker containers are running
	$(UTILS) status

ps: ## Show container status
	$(UTILS) ps

logs: ## Tail container logs (usage: make logs s=php)
	$(UTILS) logs $(s)

shell: ## Open a shell in the PHP container
	$(UTILS) shell

root-shell: ## Open a root shell in the PHP container
	$(UTILS) root-shell

##
## ── Composer ─────────────────────────────────────────────────────────────────
##

install: ## Run composer install
	$(UTILS) install

update: ## Run composer update
	$(UTILS) update

dump-autoload: ## Run composer dump-autoload
	$(UTILS) dump-autoload

##
## ── Quality ──────────────────────────────────────────────────────────────────
##

test: ## Run PHPUnit tests (usage: make test f=MyTest)
	$(UTILS) exec composer test $(if $(f),-- --filter=$(f),)

test-unit: ## Run unit tests only
	$(UTILS) exec composer test:unit

test-integration: ## Run integration tests only
	$(UTILS) exec composer test:integration

phpstan: ## Run PHPStan static analysis
	$(UTILS) phpstan

cs-check: ## Check code style with PHPCS
	$(UTILS) cs-check

cs-fix: ## Fix code style with PHPCBF
	$(UTILS) cs-fix

quality: ## Run all quality checks (test + phpstan + cs-check)
	$(UTILS) quality

##
## ── Demo ─────────────────────────────────────────────────────────────────────
##

demo: ## Show demo CLI help
	$(DEMO)

demo-state-clear: ## Clear demo state file
	$(DEMO) state clear

demo-state-show: ## Show current demo state
	$(DEMO) state show

demo-terminal-register: ## Register a terminal (usage: make demo-terminal-register name="POS-01")
	$(DEMO) terminal register --name="$(or $(name),POS-01)"

demo-terminal-list: ## List all terminals
	$(DEMO) terminal list

demo-shift-open: ## Open a shift (usage: make demo-shift-open cash=50000)
	$(DEMO) shift open --opening-cash=$(or $(cash),50000)

demo-shift-close: ## Close the current shift (usage: make demo-shift-close cash=50000)
	$(DEMO) shift close --declared-cash=$(or $(cash),50000)

demo-session-start: ## Start a POS session
	$(DEMO) session start

demo-session-new-order: ## Start a new order in the current session
	$(DEMO) session new-order

demo-session-checkout: ## Initiate checkout for the current order
	$(DEMO) session checkout

demo-session-pay: ## Request payment (usage: make demo-session-pay amount=15000 method=cash)
	$(DEMO) session pay --amount=$(or $(amount),15000) --method=$(or $(method),cash)

demo-session-complete: ## Complete the current order
	$(DEMO) session complete

demo-session-end: ## End the current POS session
	$(DEMO) session end

##
## ── Demo Scenarios ────────────────────────────────────────────────────────────
##

scenario-01: ## Scenario 1: Full shift lifecycle
	$(UTILS) exec bash demo/scenarios/01-full-shift-lifecycle.sh

scenario-02: ## Scenario 2: Checkout flow
	$(UTILS) exec bash demo/scenarios/02-checkout-flow.sh

scenario-03: ## Scenario 3: Park and resume orders
	$(UTILS) exec bash demo/scenarios/03-park-and-resume.sh

scenario-04: ## Scenario 4: Draft TTL expiry and reactivation
	$(UTILS) exec bash demo/scenarios/04-draft-ttl-expiry.sh

scenario-05: ## Scenario 5: Force close shift
	$(UTILS) exec bash demo/scenarios/05-force-close-shift.sh

scenario-06: ## Scenario 6: Offline mode and synchronization
	$(UTILS) exec bash demo/scenarios/06-offline-sync.sh

scenario-07: ## Scenario 7: Concurrency conflict detection
	$(UTILS) exec bash demo/scenarios/07-concurrency-conflict.sh

##
## ── Help ─────────────────────────────────────────────────────────────────────
##

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} \
		/^##/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 4) } \
		/^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-28s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

.PHONY: up down restart build rebuild status ps logs shell root-shell \
        install update dump-autoload \
        test test-unit test-integration phpstan cs-check cs-fix quality \
        demo demo-state-clear demo-state-show \
        demo-terminal-register demo-terminal-list \
        demo-shift-open demo-shift-close \
        demo-session-start demo-session-new-order demo-session-checkout \
        demo-session-pay demo-session-complete demo-session-end \
        scenario-01 scenario-02 scenario-03 scenario-04 scenario-05 scenario-06 scenario-07 \
        help
