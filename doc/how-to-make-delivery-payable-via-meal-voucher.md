# How to make delivery payable via meal voucher?

In `Entity/Order/Order.php` override `getMealVoucherCompatibleAmount` method :

```php
public function getMealVoucherCompatibleAmount(): int
{
    $amount = 0;

    foreach ($this->items as $item) {
        /** @var $variant ProductVariantInterface */
        $variant = $item->getVariant();

        if ($variant->isMealVoucherCompatible()) {
            $amount += $item->getTotal();
        }
    }
    
    if ($amount > 0) {
        $amount += $this->getShippingTotal();
    }

    return $amount;
}
```