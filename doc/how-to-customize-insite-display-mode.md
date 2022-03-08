# How to customize "insite" display mode template?

Create new file `src/templates/bundles/PaygreenSyliusPaygreenPlugin/Checkout/inSite.html.twig`.

Add this content:

```html
{% extends '@SyliusShop/Checkout/layout.html.twig' %}

{% block content %}

<div class="ui centered grid">
    {% include '@PaygreenSyliusPaygreenPlugin/Checkout/_iframe.html.twig' %}
</div>

{% endblock %}
```

And modify this file as you wish.