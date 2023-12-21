import { PropsWithChildren, createContext, useContext, useState } from 'react';
import { VippsMobilePayReactOptions, getMetadata } from './lib/wp-data';

/**
 * Represents the context for WordPress options.
 */
interface WPContext {
  /**
   * Sets the value of a WordPress option.
   * @param option - The name of the option.
   * @param value - The value to set for the option.
   * @returns A promise that resolves when the option is set.
   */
  setOption: (option: string, value: string | null) => Promise<void>;

  /**
   * Retrieves the value of a WordPress option.
   * @param option - The name of the option.
   * @returns The value of the option.
   */
  getOption: (option: string) => string;

  /**
   * Submits changes made to WordPress options.
   * @returns A promise that resolves when the changes are submitted.
   */
  submitChanges: (args?: { forceEnable: boolean }) => Promise<{ ok: boolean; msg: string }>;
}
const WPContext = createContext<WPContext>(null!);

/**
 * A React component that provides access to the WPContext.
 *
 * @returns The rendered component.
 */
export function WPOptionsProvider({ children }: PropsWithChildren) {
  const [values, setValues] = useState<Record<string, string | null>>(VippsMobilePayReactOptions);

  // Get the value of an option from the context.
  function getOption(key: string): string {
    return values?.[key] ?? '';
  }

  // Set the value of an option in the context.
  async function setOption(key: string, value: string | null) {
    setValues((values) => ({
      ...values,
      [key]: value ?? null
    }));
  }

  // Submits the options changed to the WordPress backend.
  async function submitChanges(args?: { forceEnable: boolean }): Promise<{ ok: boolean; msg: string }> {
    // In some cases, such as when the wizard screen is shown, we want to force enable the VippsMobilePay checkout, because we cannot display the `enabled` checkout.
    if (args?.forceEnable) {
      setOption('enabled', 'yes');
      values.enabled = 'yes';
    }

    const params = new URLSearchParams({
      action: 'vipps_update_admin_settings',
      value: JSON.stringify(values)
    });

    const response = await fetch(getMetadata('admin_url')!, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      credentials: 'include',
      body: params.toString()
    });
    return response.json();
  }

  return (
    <WPContext.Provider
      value={{
        setOption,
        getOption,
        submitChanges
      }}
    >
      {children}
    </WPContext.Provider>
  );
}

/**
 * Custom hook that provides access to the WPContext.
 * @returns The WPContext value.
 * @throws If used outside of a WPOptionsProvider.
 */
export function useWP() {
  const context = useContext(WPContext);
  if (!context) {
    throw new Error('useWP must be used within a WPOptionsProvider');
  }
  return context;
}
