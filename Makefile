# Storebunk POS - Makefile
#
# Provides convenient targets for development, testing, and demos.
# Docker must be running for all targets (use ./utils up).
#
# Usage: make <target>

.PHONY: help up down restart build rebuild status ps logs shell root-shell install update dump-autoload test test-unit test-integration phpstan cs-check cs-fix quality standards-diff standards-dry-run standards-sync-down demo demo-state-clear demo-state-show demo-terminal-register demo-terminal-list demo-shift-open demo-shift-close demo-session-start demo-session-new-order demo-session-checkout demo-session-pay demo-session-complete demo-session-end scenario-01 scenario-02 scenario-03 scenario-04 scenario-05 scenario-06 scenario-07

# ==================== Default ====================

help:
	@echo ""
	@echo "  \033[1m\033[32mStorebunk POS\033[0m"
	@echo ""
	@echo "  \033[33mDocker:\033[0m"
	@echo "    \033[32mup\033[0m                            Start Docker containers"
	@echo "    \033[32mdown\033[0m                          Stop Docker containers"
	@echo "    \033[32mrestart\033[0m                       Restart Docker containers"
	@echo "    \033[32mbuild\033[0m                         Build Docker containers"
	@echo "    \033[32mrebuild\033[0m                       Rebuild Docker containers from scratch"
	@echo "    \033[32mstatus\033[0m                        Check if Docker containers are running"
	@echo "    \033[32mps\033[0m                            Show container status"
	@echo "    \033[32mlogs\033[0m                          Tail container logs"
	@echo "    \033[32mshell\033[0m                         Open shell in PHP container"
	@echo "    \033[32mroot-shell\033[0m                    Open root shell in PHP container"
	@echo ""
	@echo "  \033[33mComposer:\033[0m"
	@echo "    \033[32minstall\033[0m                       Run composer install"
	@echo "    \033[32mupdate\033[0m                        Run composer update"
	@echo "    \033[32mdump-autoload\033[0m                 Run composer dump-autoload"
	@echo ""
	@echo "  \033[33mTest (PHPUnit):\033[0m"
	@echo "    \033[32mtest\033[0m                          Run all tests"
	@echo "    \033[32mtest-unit\033[0m                     Run unit tests only"
	@echo "    \033[32mtest-integration\033[0m              Run integration tests only"
	@echo ""
	@echo "  \033[33mQuality:\033[0m"
	@echo "    \033[32mphpstan\033[0m                       Run PHPStan static analysis"
	@echo "    \033[32mcs-check\033[0m                      Run code style check"
	@echo "    \033[32mcs-fix\033[0m                        Fix code style"
	@echo "    \033[32mquality\033[0m                       Run all quality checks"
	@echo ""
	@echo "  \033[33mStandards:\033[0m"
	@echo "    \033[32mstandards-diff\033[0m                Show differences between vendor and docs/standards/"
	@echo "    \033[32mstandards-dry-run\033[0m             Preview standards sync without applying"
	@echo "    \033[32mstandards-sync-down\033[0m           Sync standards from vendor to docs/standards/"
	@echo ""
	@echo "  \033[33mDemo CLI:\033[0m"
	@echo "    \033[32mdemo\033[0m                          Show demo CLI help"
	@echo "    \033[32mdemo-state-clear\033[0m              Clear demo state file"
	@echo "    \033[32mdemo-state-show\033[0m               Show current demo state"
	@echo "    \033[32mdemo-terminal-register\033[0m        Register a terminal"
	@echo "    \033[32mdemo-terminal-list\033[0m            List all terminals"
	@echo "    \033[32mdemo-shift-open\033[0m               Open a shift"
	@echo "    \033[32mdemo-shift-close\033[0m              Close the current shift"
	@echo "    \033[32mdemo-session-start\033[0m            Start a POS session"
	@echo "    \033[32mdemo-session-new-order\033[0m        Start a new order"
	@echo "    \033[32mdemo-session-checkout\033[0m         Initiate checkout"
	@echo "    \033[32mdemo-session-pay\033[0m              Request payment"
	@echo "    \033[32mdemo-session-complete\033[0m         Complete the order"
	@echo "    \033[32mdemo-session-end\033[0m              End the POS session"
	@echo ""
	@echo "  \033[33mDemo Scenarios:\033[0m"
	@echo "    \033[32mscenario-01\033[0m                   Full shift lifecycle"
	@echo "    \033[32mscenario-02\033[0m                   Checkout flow"
	@echo "    \033[32mscenario-03\033[0m                   Park and resume orders"
	@echo "    \033[32mscenario-04\033[0m                   Draft TTL expiry and reactivation"
	@echo "    \033[32mscenario-05\033[0m                   Force close shift"
	@echo "    \033[32mscenario-06\033[0m                   Offline mode and synchronization"
	@echo "    \033[32mscenario-07\033[0m                   Concurrency conflict detection"
	@echo ""

# ==================== Docker ====================

up: ## Start containers
	./utils up

down: ## Stop containers
	./utils down

restart: ## Restart containers
	./utils restart

build: ## Build containers
	./utils build

rebuild: ## Rebuild containers from scratch
	./utils rebuild

status: ## Check if Docker containers are running
	./utils status

ps: ## Show container status
	./utils ps

logs: ## Tail container logs (usage: make logs s=php)
	./utils logs $(s)

shell: ## Open shell in PHP container
	./utils shell

root-shell: ## Open root shell in PHP container
	./utils root-shell

# ==================== Composer ====================

install: ## Run composer install
	./utils install

update: ## Run composer update
	./utils update

dump-autoload: ## Run composer dump-autoload
	./utils dump-autoload

# ==================== Test (PHPUnit) ====================

test: ## Run PHPUnit tests (usage: make test f=MyTest)
	./utils exec composer test $(if $(f),-- --filter=$(f),)

test-unit: ## Run unit tests only
	./utils exec composer test:unit

test-integration: ## Run integration tests only
	./utils exec composer test:integration

# ==================== Quality ====================

phpstan: ## Run PHPStan static analysis
	./utils phpstan

cs-check: ## Check code style
	./utils cs-check

cs-fix: ## Fix code style
	./utils cs-fix

quality: ## Run all quality checks (test, phpstan, cs-check)
	./utils quality

# ==================== Standards ====================

standards-diff: ## Show differences between vendor and docs/standards/
	./utils exec vendor/bin/standards --diff-only

standards-dry-run: ## Preview standards sync without applying
	./utils exec vendor/bin/standards --dry-run

standards-sync-down: ## Sync standards from vendor to docs/standards/
	./utils exec vendor/bin/standards

# ==================== Demo CLI ====================

demo: ## Show demo CLI help
	./utils demo

demo-state-clear: ## Clear demo state file
	./utils demo state clear

demo-state-show: ## Show current demo state
	./utils demo state show

demo-terminal-register: ## Register a terminal (usage: make demo-terminal-register name="POS-01")
	./utils demo terminal register --name="$(or $(name),POS-01)"

demo-terminal-list: ## List all terminals
	./utils demo terminal list

demo-shift-open: ## Open a shift (usage: make demo-shift-open cash=50000)
	./utils demo shift open --opening-cash=$(or $(cash),50000)

demo-shift-close: ## Close the current shift (usage: make demo-shift-close cash=50000)
	./utils demo shift close --declared-cash=$(or $(cash),50000)

demo-session-start: ## Start a POS session
	./utils demo session start

demo-session-new-order: ## Start a new order in the current session
	./utils demo session new-order

demo-session-checkout: ## Initiate checkout for the current order
	./utils demo session checkout

demo-session-pay: ## Request payment (usage: make demo-session-pay amount=15000 method=cash)
	./utils demo session pay --amount=$(or $(amount),15000) --method=$(or $(method),cash)

demo-session-complete: ## Complete the current order
	./utils demo session complete

demo-session-end: ## End the current POS session
	./utils demo session end

# ==================== Demo Scenarios ====================

scenario-01: ## Scenario 1: Full shift lifecycle
	./utils exec bash demo/scenarios/01-full-shift-lifecycle.sh

scenario-02: ## Scenario 2: Checkout flow
	./utils exec bash demo/scenarios/02-checkout-flow.sh

scenario-03: ## Scenario 3: Park and resume orders
	./utils exec bash demo/scenarios/03-park-and-resume.sh

scenario-04: ## Scenario 4: Draft TTL expiry and reactivation
	./utils exec bash demo/scenarios/04-draft-ttl-expiry.sh

scenario-05: ## Scenario 5: Force close shift
	./utils exec bash demo/scenarios/05-force-close-shift.sh

scenario-06: ## Scenario 6: Offline mode and synchronization
	./utils exec bash demo/scenarios/06-offline-sync.sh

scenario-07: ## Scenario 7: Concurrency conflict detection
	./utils exec bash demo/scenarios/07-concurrency-conflict.sh
