import { PropsWithChildren, createContext, useContext, useEffect, useState } from 'react';
import { initialSettings, SettingsData, gettext, isFieldVisibleForBrand } from './lib/wp-data';

interface WPContext {
  settings: SettingsData;
  values: Record<string, string | number>;
  isDirty: boolean;
  setOption: (key: string, value: string) => void;
  submitChanges: () => Promise<SettingsData>;
}

const WPContext = createContext<WPContext>(null!);

/** Keep unsaved form state separate from the canonical values returned by WooCommerce. */
export function WPOptionsProvider({ children }: PropsWithChildren): JSX.Element {
  const [settings, setSettings] = useState(initialSettings);
  const [values, setValues] = useState(settings.values);
  const isDirty = JSON.stringify(values) !== JSON.stringify(settings.values);

  useEffect(() => {
    if (!isDirty) return;
    const beforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', beforeUnload);
    return () => window.removeEventListener('beforeunload', beforeUnload);
  }, [isDirty]);

  function setOption(key: string, value: string) {
    setValues((current) => ({ ...current, [key]: value }));
  }

  async function submitChanges(): Promise<SettingsData> {
    // The PHP controller maps one gateway's field keys to WooCommerce post keys.
    // Omit fields for the other brand; the controller preserves their stored
    // values when it delegates this save to WooCommerce.
    const brand = values.brand === 'mobilepay' ? 'mobilepay' : 'vipps';
    const editable = new Set(settings.sections.flatMap((section) =>
      Object.entries(section.fields).filter(([, field]) => !field.disabled &&
        isFieldVisibleForBrand(field, brand) &&
        ['checkbox', 'text', 'password', 'number', 'select', 'page_dropdown'].includes(field.type))
        .map(([key]) => key)
    ));
    const params = new URLSearchParams({ action: settings.action, nonce: settings.nonce });
    Object.entries(values).filter(([key]) => editable.has(key)).forEach(([key, value]) => {
      params.append(`values[${key}]`, String(value));
    });
    const response = await fetch(settings.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      credentials: 'same-origin',
      body: params.toString(),
    });
    const result = await response.json().catch(() => null);
    if (!response.ok || result?.success !== true || !result.data?.values) {
      throw new Error(result?.data?.message ?? gettext('save_failed'));
    }
    const saved = result.data as SettingsData;
    if (!saved.saved) {
      throw new Error(saved.form_errors?.join('\n') || gettext('save_failed'));
    }
    // The gateway may normalize values or change conditional fields during save.
    setSettings(saved);
    setValues(saved.values);
    return saved;
  }

  return <WPContext.Provider value={{ settings, values, isDirty, setOption, submitChanges }}>{children}</WPContext.Provider>;
}

export function useWP(): WPContext {
  const context = useContext(WPContext);
  if (!context) throw new Error('useWP must be used within a WPOptionsProvider');
  return context;
}
