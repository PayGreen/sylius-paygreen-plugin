# How to display the amount payable in meal voucher in cart?

In `views/Checkout/SelectPayment/_payment.html.twig` add:

```html
<div class="ui segment">
    <div class="ui dividing header">{{ 'sylius.ui.payment'|trans }} #{{ loop.index }}</div>
    <div class="ui fluid stackable items">
        {{ form_errors(form.method) }}

        {% for key, choice_form in form.method %}
            {% if form.method.vars.choices[key].data.gatewayConfig.factoryName == 'paygreen' %}
                {% if form.method.vars.choices[key].data.gatewayConfig.config['payment_type'] == 'TRD'
                    and order.getMealVoucherCompatibleAmount == 0 %}
                    {# Don't show TRD payment choice #}
                {% else %}
                    {% include '@SyliusShop/Checkout/SelectPayment/_choice.html.twig' with {'form': choice_form, 'method': form.method.vars.choices[key].data} %}
                {% endif %}
            {% else %}
                {% include '@SyliusShop/Checkout/SelectPayment/_choice.html.twig' with {'form': choice_form, 'method': form.method.vars.choices[key].data} %}
            {% endif %}
        {% else %}
            {% include '@SyliusShop/Checkout/SelectPayment/_unavailable.html.twig' %}
        {% endfor %}
    </div>
</div>
```