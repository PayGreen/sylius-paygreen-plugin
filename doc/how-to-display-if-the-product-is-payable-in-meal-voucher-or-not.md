# How to display if the product is payable in meal voucher or not?

The compatibility information is stored in the product variant.

You can use:

```html
{% set variant = product|sylius_resolve_variant %}

{{ variant.isMealVoucherCompatible }}
```