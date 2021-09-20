<p align="center">
  <a href="https://paygreen.io/" target="_blank">
    <img alt="paygreen logo" width="250px" src="https://paygreen.github.io/images/email/logo/paygreen/base.png" />
  </a>
</p>
<p align="center">
  <a href="https://sylius.com" target="_blank">
      <img alt="sylius logo" width="250px" src="https://demo.sylius.com/assets/shop/img/logo.png" />
  </a>
</p>

<h1 align="center">Sylius payment module with <a target="_blank" href="https://paygreen.io/">Paygreen</a></h1>

## Quickstart Installation

```bash
     composer require paygreen/sylius-paygreen-plugin
```
## Configuration

<p>
The module configuration is located in the Payment method section of the admin panel.

Connect your Paygreen account to the module with your public key and your private key.

You will have to select Bank card or Dematerialized Restaurant Tickets according to your needs. 

In the .env file, you have to configure the PAYGREEN_URL_API (production or sandbox) depending of your customer account.
</p>

## Contribution

### Installation:

```bash
$ composer install
$ (cd tests/Application && yarn install)
$ (cd tests/Application && yarn build)
$ (cd tests/Application && APP_ENV=test bin/console assets:install public)

$ (cd tests/Application && APP_ENV=test bin/console doctrine:database:create)
$ (cd tests/Application && APP_ENV=test bin/console doctrine:schema:create)
```

To be able to setup a plugin's database, remember to configure you database credentials in `tests/Application/.env` and `tests/Application/.env.test`.

### Running plugin tests

- PHPSpec

  ```bash
  $ composer phpspec
  ```

- Behat

  ```bash
  $ composer behat
  ```

- All tests (phpspec & behat)

  ```bash
  $ composer test
  ```