# How to display the amount payable in meal voucher in the basket?

In `views/Cart/Summary/_totals.html.twig` add:

```html
<tr>
    <td>Meal Voucher total:</td>
    <td class="right aligned">
        {{ money.convertAndFormat(cart.getMealVoucherCompatibleAmount) }}
    </td>
</tr>
```