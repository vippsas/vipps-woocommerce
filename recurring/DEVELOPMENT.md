# Vipps MobilePay Recurring - Development docs

## Purchase service regression checks

Run the focused checks with PHP:

```bash
php recurring/tests/unit/purchase-test.php
```

These checks use WordPress and WooCommerce doubles. They load the recurring core
bootstrap with Checkout disabled and exercise preparation and completion without
the Checkout class or a browser session. They also check the Checkout contact
adapter, repeated completion, account creation failures, and customer assignment
before the payment-status check. They do not replace live WooCommerce/Vipps tests.

## Updating language files

The easiest way to update language files is to first and foremost generate an up-to-date .pot file using `wp-cli`:

```bash
wp i18n make-pot . languages/vipps-recurring-payments-gateway-for-woocommerce.pot --exclude=node_modules,assets/build
```

Now you can translate the existing languages, or create new ones, using Loco translate or a similar plugin or tool.

Remember to make a JSON file from each language after editing the `.po` files. The JSON files are used by the front-end.

```bash
wp i18n make-json languages --no-purge --use-map=build.map.json
```
