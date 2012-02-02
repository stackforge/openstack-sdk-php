PROJ := 'hpcloud-php'
SRCDIR := src
TESTS := test/Tests
VERSION := 'DEV'
DATE := `date "+%Y%m%d"`
GROUP := 'deprecated'

VFILES = src/HPCloud

docs :
	@cat ./config.doxy | sed 's/-UNSTABLE%/$(VERSION)/' | doxygen -

test :
	phpunit --color -v  --exclude-group=deprecated $(TESTS)

test-group :
	phpunit --color -v --group $(GROUP) $(TESTS)

lint : $(SRCDIR)
	find $(SRCDIR) -iname *.php -exec php -l {} ';'

dist: tar

tar: 
	@echo $(PROJ)-$(VERSION)-$(DATE).tgz
	# @tar -zcvf $(PROJ)-$(VERSION)-$(DATE).tgz $(SRCDIR)

.PHONY: docs test dist tar lint
