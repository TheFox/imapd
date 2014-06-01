
RELEASE_VERSION = 0.3.0-dev
RELEASE_NAME = phpchat2

DELETE = rm -rf
MKDIR = mkdir -p
TAR = tar
GZIP = gzip
MV = mv -i
PHPCS = vendor/bin/phpcs
PHPUNIT = vendor/bin/phpunit


all: install tests

install: composer.phar

update: composer.phar
	./composer.phar self-update
	./composer.phar update -vv

composer.phar:
	curl -sS https://getcomposer.org/installer | php
	./composer.phar install

$(PHPCS): composer.phar

tests: test_phpcs test_phpunit

test_phpcs: $(PHPCS) vendor/thefox/phpcsrs/Standards/TheFox
	$(PHPCS) -v -s --report=full --report-width=160 --standard=vendor/thefox/phpcsrs/Standards/TheFox src tests

test_phpunit: $(PHPUNIT) phpunit.xml
	$(PHPUNIT)

release:
	find . -name .DS_Store -exec rm {} \;
	$(MKDIR) releases
	$(TAR) -cpf $(RELEASE_NAME)-$(RELEASE_VERSION).tar \
		README.md \
		composer.json \
		bootstrap.php \
		console.php cronjob.php functions.php kernel.php \
		src \
		vendor/autoload.php \
		vendor/composer \
		vendor/monolog \
		vendor/psr \
		vendor/rhumsaa \
		vendor/symfony \
		vendor/thefox \
		vendor/ulrichsg
	$(GZIP) -9 -f $(RELEASE_NAME)-$(RELEASE_VERSION).tar
	$(MV) ${RELEASE_NAME}-${RELEASE_VERSION}.tar.gz releases

clean:
	$(DELETE) composer.lock composer.phar
	$(DELETE) vendor/*
	$(DELETE) vendor
