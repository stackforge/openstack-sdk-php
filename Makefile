
SRCDIR := src
TESTS := test/Tests
VERSION := 'DEV'
GROUP := 'deprecated'

VFILES = src/HPCloud

docs :
	@cat ./config.doxy | sed 's/-UNSTABLE%/$(VERSION)/' | doxygen -

test :
	phpunit --color -v  --exclude-group=deprecated $(TESTS)

test-group :
	phpunit --color -v --group $(GROUP) $(TESTS)

lint : src/HPCloud/*.php
	php -l $?

dist: tar

tar: ;
	

.PHONY: docs test dist tar lint
