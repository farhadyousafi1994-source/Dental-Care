# Atelier Laravel API

Read [the main README](../README.md) and [local PHP/Composer/MySQL setup](../docs/LOCAL_SETUP.md).

Run `php artisan cms:doctor` after installation. The browser frontend is in `../frontend`, not Laravel's default Vite resources.

`php artisan test --configuration=phpunit.mysql.xml` uses the disposable `website_cms_test` database. Never point it at live data. PHP syntax was checked in the Arena workspace; Laravel/MySQL integration execution must still be completed locally or through the supplied GitHub workflow.
