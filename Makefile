PHP=$(shell which php)
COMPOSER_HOST=$(shell which composer)
COMPOSER_VAR=$(shell echo ./var/composer)
ifeq ($(COMPOSER_HOST),)
    COMPOSER=$(COMPOSER_VAR)
else
    COMPOSER=$(COMPOSER_HOST)
endif

.PHONY: tests audit test_cs test_yaml test_container test_unit test_stan

tests: audit test_cs test_yaml test_container test_unit test_stan

audit:
	$(COMPOSER) audit
test_unit:
	./bin/phpunit
test_cs:
	./bin/php-cs-fixer fix
test_stan:
	./bin/phpstan analyze -c ./phpstan.neon
test_yaml:
	./bin/console lint:yaml ./config
test_container:
	./bin/console lint:container

install: install_composer install_vendor audit tests

install_vendor: $(PHP)
	$(PHP) -d allow_url_fopen=On $(COMPOSER) install
install_composer: $(PHP)
	$(PHP) -d allow_url_fopen=On -r "copy('https://getcomposer.org/installer', './var/composer-setup.php');"
	$(PHP) -d allow_url_fopen=On ./var/composer-setup.php --install-dir=./var --filename=composer
	$(PHP) -r "unlink('./var/composer-setup.php');"
install_prod: $(PHP)
	$(PHP) -d allow_url_fopen=On $(COMPOSER) install --no-dev --classmap-authoritative --no-progress --no-interaction