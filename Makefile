.PHONY: composer archive clean

composer:
	rm -rf vendor/
	exec composer install
	@echo

archive:
	rm -f shifter-wp-git.zip
	zip shifter-wp-git.zip install-from-github.php inc/* vendor/*

clean:
	rm -f shifter-wp-git.zip
	rm -rf vendor/
