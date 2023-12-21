/**
 * Entry point for WordPress data, this file is meant to be a centralised place for all WordPress data,
 * so that we can have a clear overview of what data is available and where it comes from.
 */

/**
 * Type definition for the extended window object that includes WordPress data.
 */
type WPWindow = (Window & typeof globalThis) & {
  VippsMobilePayReactTranslations?: Record<string, string>;
  VippsMobilePayReactOptions?: Record<string, string>;
  VippsMobilePayReactMetadata?: Record<string, string>;
};

/**
 * The extended window object that includes WordPress data.
 */
const wpWindow = window as WPWindow;

/**
 * Throws an error if VippsMobilePayReactTranslations is not found in the wpWindow object.
 * Make sure to include this using wp_localize_script().
 */
if (!wpWindow.VippsMobilePayReactTranslations) {
  throw new Error('VippsMobilePayReactTranslations not found, make sure to include this using wp_localize_script()');
}

/**
 * Throws an error if VippsMobilePayReactOptions is not found in the wpWindow object.
 * Make sure to include this using wp_localize_script().
 */
if (!wpWindow.VippsMobilePayReactOptions) {
  throw new Error('VippsMobilePayReactOptions not found, make sure to include this using wp_localize_script()');
}

/**
 * Throws an error if VippsMobilePayReactMetadata is not found in the wpWindow object.
 * Make sure to include this using wp_localize_script().
 */
if (!wpWindow.VippsMobilePayReactMetadata) {
  throw new Error('VippsMobilePayReactMetadata not found, make sure to include this using wp_localize_script()');
}

/**
 * Retrieves the translated string for the given message ID.
 * If the translation is not found, the original message ID is returned.
 * @param msgid - The message ID to be translated.
 * @returns The translated string or the original message ID.
 */
export function gettext(msgid: string): string {
  return wpWindow.VippsMobilePayReactTranslations?.[msgid] ?? msgid;
}

/**
 * Retrieves the metadata value for the given key.
 * If the metadata value is not found, null is returned.
 * @param key - The key of the metadata value.
 * @returns The metadata value or null.
 */
export function getMetadata(key: string): string | null {
  return wpWindow.VippsMobilePayReactMetadata?.[key] ?? null;
}

/**
 * The VippsMobilePayReactTranslations object from the wpWindow.
 */
const { VippsMobilePayReactTranslations, VippsMobilePayReactOptions, VippsMobilePayReactMetadata } = wpWindow;

/**
 * Exports the VippsMobilePayReactTranslations object.
 */
export { VippsMobilePayReactTranslations, VippsMobilePayReactOptions, VippsMobilePayReactMetadata };
