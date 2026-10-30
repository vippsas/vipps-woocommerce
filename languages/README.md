# Updating translations

Run these commands from the plugin root (`woo-vipps/`) with WP-CLI installed.

1. Extract source strings and update the language catalogs:

   ```bash
   wp i18n make-pot . languages/woo-vipps.pot --domain=woo-vipps
   wp i18n update-po languages/woo-vipps.pot
   ```

2. Edit the translations in the `.po` files in your editor/Poedit/Loco Translate/etc.
3. Save and compile the translations:

   ```bash
   wp i18n make-mo languages
   wp i18n make-php languages
   ```
