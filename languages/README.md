<!-- START_METADATA
---
draft: true
---
END_METADATA -->

# Updating translations

Run these commands from this directory with WP-CLI installed.

1. Extract source strings and update the language catalogs:

   ```bash
   wp i18n make-pot .. woo-vipps.pot --domain=woo-vipps
   wp i18n update-po woo-vipps.pot
   ```

2. Edit the translations in the `.po` files in your editor/Poedit/Loco Translate/etc.
3. Save and compile the translations:

   ```bash
   wp i18n make-mo .
   wp i18n make-php .
   ```
