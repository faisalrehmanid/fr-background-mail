### Requirements

PHP ^7.0
PHP Gearman Extension [Reference](https://www.php.net/manual/en/book.gearman.php)

### Features

1. Dynamic dedicated workers for each job, start workers on the fly
2. Rich configurations. Configure number of dedicated workers for each background job in config
3. No execution delay. Start execution job immediately when submitted
4. Retry to send not delivered emails. Configure number of retry in config
5. Remove workers from memory when job completed and remove stucked workers as well
6. Don't need to restart workers when make any change to code
7. Support both SMTP authenticated and non-authenticated mails
8. Support up to 6 attachments in each email
9. Support MySQL and Oracle database storage for sent log
10. Throw errors and output in `error-logs-gearman` folder at root. Very useful for debugging

### How to use

Check out the `examples` folder given in package.

### How to test

1. Point to /vendor/bin dir `cd ./vendor/bin`
2. Execute PHPUnit tests: `phpunit --configuration ./../../tests/phpunit.xml`
3. To check phpunit version: `phpunit --version`
