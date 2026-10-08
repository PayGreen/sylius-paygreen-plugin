# Sylius PayGreen Payum Plugin

Fresh Sylius payment plugin skeleton for PayGreen hosted payment pages.

The integration uses `paygreen/paygreen-php` as the only communication layer with PayGreen. The plugin builds SDK models, calls SDK client methods, and maps SDK response data back into Payum/Sylius payment details.

## Targets

- Sylius `^1.14`, prepared for `^2.0`
- Symfony `^6.4`, prepared for `^7.0`
- PHP `^8.2`, prepared for PHP `8.3+`

## Includes

- Sylius plugin bundle skeleton
- Payum gateway factory named `paygreen`
- Sylius payment method configuration form
- Payum capture, notify, and status actions
- Sylius order/payment to PayGreen SDK `PaymentOrder` conversion
- Redirect to PayGreen `hosted_payment_url`
- Return controller skeleton
- Webhook controller skeleton
- PayGreen status mapping
- Basic PHPUnit tests

## Configuration

Register the bundle in the host application:

```php
// config/bundles.php
return [
    PayGreen\SyliusPayumPlugin\PayGreenSyliusPayumPlugin::class => ['all' => true],
];
```

Import routes:

```yaml
# config/routes/paygreen.yaml
paygreen_sylius_payum_plugin:
    resource: '@PayGreenSyliusPayumPlugin/Resources/config/routes.yaml'
```

Install an HTTPlug-compatible HTTP client for the PayGreen SDK, for example:

```bash
composer require php-http/curl-client
```

Then add a Sylius payment method using the `PayGreen` gateway and configure:

- Shop ID
- Public key
- Secret key
- Environment

### Webhook listener URL

When a PayGreen payment method is saved, the plugin automatically registers or verifies the PayGreen webhook listener through the PayGreen SDK. It first calls the PayGreen API to find an existing listener for the generated webhook URL, then creates one when needed and stores the returned HMAC key in the gateway config.

By default, the listener URL is generated from the Symfony route `paygreen_payment_webhook`. In local or proxied environments, you can override the public base URL with:

```dotenv
DEFAULT_LISTENER_URI=https://your-public-domain.example
```

If `DEFAULT_LISTENER_URI` has no path, the plugin appends the webhook route path automatically. You may also provide the full listener URL:

```dotenv
DEFAULT_LISTENER_URI=https://your-public-domain.example/payment/paygreen/webhook
```

The value is meant for environment configuration only; it is not displayed in the Sylius admin. If the generated listener URL is local (`localhost`, `127.0.0.1`, or `::1`), listener registration is skipped because PayGreen cannot call local URLs.

## Meal vouchers

Meal voucher eligibility is configured **per product variant**, exactly as in the
previous PayGreen Sylius plugin. Flagging a variant as eligible adds its amount to
the PayGreen `eligible_amounts` sent at payment time.

### 1. Make your `ProductVariant` meal-voucher aware

Have your Sylius product variant entity implement the plugin interface and use the
provided trait:

```php
use PayGreen\SyliusPayumPlugin\Entity\MealVoucherAwareInterface;
use PayGreen\SyliusPayumPlugin\Entity\MealVoucherAwareTrait;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;

class ProductVariant extends BaseProductVariant implements MealVoucherAwareInterface
{
    use MealVoucherAwareTrait;
}
```

The trait adds a single `meal_voucher_compatible` boolean column (default `false`).

### 2. Generate and run the Doctrine migration

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

### 3. Set eligibility in the admin

No template override is required. As soon as your `ProductVariant` implements
`MealVoucherAwareInterface`, the plugin automatically injects an **"Eligible for meal
vouchers" checkbox** into the product variant form
(`Catalog → Products → … → Variants`). Tick it on every variant that can be paid with
meal vouchers.

> Note: eligibility lives on the **variant**, not the product. For a simple product,
> Sylius edits its default variant, so the checkbox appears directly on the product
> edit page.

### How it works

When at least one order item uses an eligible variant, the plugin sends the summed
amount in PayGreen V3 `eligible_amounts` under the **`food`** domain (the domain that
groups meal voucher platforms such as `swile`, `restoflash` and `conecs`).
`eligible_amounts` is keyed by domain (`ecommerce`, `travel`, `food`), not by
platform. API calls still go only through `paygreen/paygreen-php`.

To read a variant's eligibility in your own templates:

```twig
{% set variant = product|sylius_resolve_variant %}
{{ variant.mealVoucherCompatible ? 'Payable with meal vouchers' : '' }}
```
