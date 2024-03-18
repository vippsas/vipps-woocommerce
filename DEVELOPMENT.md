# Vipps MobilePay Recurring - Development docs

## Updating language files

The easiest way to update language files is to first and foremost generate an up-to-date .pot file using wp-cli:

```bash
wp i18n make-pot . languages/vipps-recurring-payments-gateway-for-woocommerce.pot --exclude=node_modules,assets
```

Now you can translate the existing languages, or create new ones, using Loco translate or a similar plugin or tool.

Remember to make a json file from each language after editing the .po files. The json files are used by the front-end.

```bash
wp i18n make-json languages --no-purge --use-map=src/build.map.json
```
