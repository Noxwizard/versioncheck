To install:
1. Import the `versioncheck.sql` file.
1. Rename `config.sample.php` to `config.php` and fill out the provider information.
1. Update any hardcoded references to `versioncheck.net`
1. Use [Composer](https://getcomposer.org/) to install the dependencies:
   * `composer install`
1. Create two cronjobs:
   * `check.php` to do the version checks
   * `notify.php` to send out noficiation emails