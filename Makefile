.PHONY: composer archive clean

composer:
	rm -rf vendor/
	exec composer install
	@echo

archive:
	rm -f shifter-github.zip
	zip -r shifter-github.zip *.php *.md inc/* vendor/*

clean:
	rm -f shifter-github.zip
	rm -rf vendor/
