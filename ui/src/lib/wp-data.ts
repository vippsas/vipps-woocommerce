/**
 * Entry point for WordPress data, this file is meant to be a centralised place for all WordPress data,
 * so that we can have a clear overview of what data is available and where it comes from.
 */
type WPWindow = (Window & typeof globalThis) & {
  VippsMobilePayReactTranslations?: Record<string, string>;
  VippsMobilePayReactOptions?: Record<string, string>;
  VippsMobilePayReactMetadata?: Record<string, string>;
};
const wpWindow = window as WPWindow;

if (!wpWindow.VippsMobilePayReactTranslations) {
  throw new Error('VippsMobilePayReactTranslations not found, make sure to include this using wp_localize_script()');
}
if (!wpWindow.VippsMobilePayReactOptions) {
  throw new Error('VippsMobilePayReactOptions not found, make sure to include this using wp_localize_script()');
}
if (!wpWindow.VippsMobilePayReactMetadata) {
  throw new Error('VippsMobilePayReactMetadata not found, make sure to include this using wp_localize_script()');
}

export function gettext(msgid: string): string {
  return wpWindow.VippsMobilePayReactTranslations?.[msgid] ?? msgid;
}

export function getMetadata(key: string): string | null {
  return wpWindow.VippsMobilePayReactMetadata?.[key] ?? null;
}

const { VippsMobilePayReactTranslations, VippsMobilePayReactOptions, VippsMobilePayReactMetadata } = wpWindow;
export { VippsMobilePayReactTranslations, VippsMobilePayReactOptions, VippsMobilePayReactMetadata };
