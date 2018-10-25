.PHONY: test
test:
	./vendor/bin/phpunit

.PHONY: generate
generate:
	php tools/user_agent_sorter.php > tests/user_agents.tmp.json && mv tests/user_agents.tmp.json tests/user_agents.json
	php tools/constant_generator.php > src/browser_constants.php

.PHONY: init
init:
	php tools/init_user_agent.php > tests/user_agents.tmp.json && mv tests/user_agents.tmp.json tests/user_agents.json
	make generate