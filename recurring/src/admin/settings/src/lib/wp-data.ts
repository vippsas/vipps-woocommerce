/** PHP owns the live WooCommerce field definitions and translated text. */
export interface FormField {
  type: string;
  title: string;
  label: string;
  description: string;
  default: string | number;
  disabled: boolean;
  options: Record<string, string>;
}

export interface SettingsSection {
  id: string;
  title: string;
  description: string;
  fields: Record<string, FormField>;
}

export interface SettingsData {
  values: Record<string, string | number>;
  sections: SettingsSection[];
  ajax_url: string;
  action: string;
  nonce: string;
  native_url: string;
  translations: Record<string, string>;
  saved?: boolean;
  connection_ok?: boolean;
  form_errors?: string[];
}

const wpWindow = window as Window & { VippsRecurringReactSettings?: SettingsData };
if (!wpWindow.VippsRecurringReactSettings) {
  throw new Error('VippsRecurringReactSettings is missing; load this screen through WordPress.');
}
export const initialSettings = wpWindow.VippsRecurringReactSettings;

export function gettext(key: string): string {
  return initialSettings.translations[key] ?? key;
}
