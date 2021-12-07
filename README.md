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

## Installation

Require the plugin :

```bash
composer require paygreen/sylius-paygreen-plugin
```

1. Your `ProductVariant` entity needs to implement de `MealVoucherAwareInterface` and use the `MealVoucherAwareTrait`.
2. Your `Order` entity needs to implement de `MealVoucherableInterface` and use the `MealVoucherableTrait`.
3. You need to run a diff of your doctrine's migrations: `bin/console doctrine:migrations:diff`. Don't forget to run it! (`bin/console doctrine:migrations:migrate`)
4. Copy the template (we update the Product and ProductVariant forms):
   ```
   mkdir -p templates/bundles/SyliusAdminBundle
   cp -Rv vendor/paygreen/sylius-paygreen-plugin/src/Resources/views/SyliusAdminBundle/ templates/bundles/
   ```

## Configuration

![Gateway configuration](https://github.com/PayGreen/sylius-paygreen-plugin/blob/master/doc/gateway_configuration.png?raw=true)

The module configuration is located in the Payment method section of the admin panel.

Connect your Paygreen account to the module with your public key and your private key.

You will have to select `Bank card` or `Meal Voucher` according to your needs. 

In the `.env` file, you have to configure the `PAYGREEN_URL_API` (production or sandbox) depending on your customer account.

```
PAYGREEN_URL_API=https://paygreen.fr/api/
```

## Cookbook

- [How to display the amount payable in meal voucher in cart?](https://github.com/PayGreen/sylius-paygreen-plugin/blob/master/doc/how-to-display-the-amount-payable-in-meal-voucher-in-cart.md)
- [How to display if the product is payable in meal voucher or not?](https://github.com/PayGreen/sylius-paygreen-plugin/blob/master/doc/how-to-display-if-the-product-is-payable-in-meal-voucher-or-not.md)
- [How to make delivery payable via meal voucher?](https://github.com/PayGreen/sylius-paygreen-plugin/blob/master/doc/how-to-make-delivery-payable-via-meal-voucher.md)
- [How to hide the meal ticket payment if it is not available for this cart?](https://github.com/PayGreen/sylius-paygreen-plugin/blob/master/doc/how-to-hide-the-meal-voucher-payment-method-if-it-is-not-available-for-this-cart.md)

## Contribution

### Installation:

```bash
$ composer install
$ (cd tests/Application && yarn install)
$ (cd tests/Application && yarn build)
$ (cd tests/Application && APP_ENV=test bin/console assets:install public)

$ (cd tests/Application && APP_ENV=test bin/console doctrine:database:create)
$ (cd tests/Application && APP_ENV=test bin/console doctrine:schema:create)
$ (cd tests/Application && APP_ENV=test bin/console sylius:fixtures:load)
```

To be able to setup a plugin's database, remember to configure you database credentials in `tests/Application/.env` and `tests/Application/.env.test`.

### Start local server
```bash
$ (cd tests/Application && APP_ENV=test php -S localhost:8080 -t public)
```

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