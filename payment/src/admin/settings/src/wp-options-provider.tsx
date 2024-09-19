import { PropsWithChildren, createContext, useContext, useState } from 'react';
import { VippsMobilePayReactOptions, getMetadata, gettext } from './lib/wp-data';

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
   * Determines if a WordPress option exists
   * @param option - The name of the option.
   * @returns true iff the option exists
   */
  hasOption: (option: string) => boolean;

  /**
   * Sets the values of all options
   * @param options - The options
   * @returns void
   */
  setOptions: (options: Record<string, string | null>) => Promise<void>;

  /**
   * Submits changes made to WordPress options.
   * @returns A promise that resolves when the changes are submitted.
   */
  submitChanges: (args?: { forceEnable: boolean }) => Promise<{
    /**
     * Whether the connection to the Vipps servers is OK.
     */
    connection_ok: boolean;
    /**
     * The connection message, whether the connection is OK or not.
     */
    connection_msg: string;
    /**
     * Whether the form fields are OK.
     */
    form_ok: boolean;
    /**
     * The form field errors, if any. 
     */
    form_errors: string[];
    /**
     * The updated options.
     */
    options: Record<string, string | null>;
  }>;
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
    // retrieve the current value for this option key from the values
    const currentValue = values[key];
    // try to retrieve the default value for this option key from the translations
    // IOK 2024-06-04 a bit hackish: The translations store the form fields from the gateway,
    // one of these is the default value of the setting. If the setting doesn't exist, gettext
    // will just return the key used to retrieve it. But this is encapsulated here, so nbd.
    const defaultkey = key + '.default';
    const defaultExists = gettext(defaultkey);
    const defaultValue = (defaultExists != defaultkey) ? defaultExists : null;
    return currentValue ?? defaultValue ?? '';
  }

  // True if the option actually exists in the settings IOK 2024-06-04
  function hasOption(key: string): boolean {
      // see getOption to see what is going on here IOK 2024-06-04
      // basically if an option has a default, it exists, otherwise not. IOK 2024-06-05
      const defaultkey = key + '.default';
      const defaultExists = gettext(defaultkey);
      return defaultExists != defaultkey;
  }

  // Set the value of an option in the context.
  async function setOption(key: string, value: string | null) {
    setValues((values) => ({
      ...values,
      [key]: value ?? null
    }));
  }

  async function setOptions(options: Record<string, string | null>) {
    setValues(options);
  }

  // Submits the options changed to the WordPress backend.
  async function submitChanges(args?: { forceEnable: boolean }) {
    // In some cases, we want to force-enable the "Enable Vipps MobilePay" option, such as when the user sets up the plugin for the first time in the wizard screen.
    if (args?.forceEnable) {
      setOption('enabled', 'yes');
      values.enabled = 'yes';
    }

    // Grab the nonce to avoid csrf IOK 2024-01-03
    const nonce: string = (document.getElementById('vippsadmin_nonce') as HTMLInputElement).value;

    // IOK 2024-01-03 curiously, using JSON.stringify on the values here ends up with extra slashes in the
    // strings after stringifying the URLSearchParams, which are not handled properly by the php backend...
    const params = new URLSearchParams({
      action: 'vipps_update_admin_settings',
      vippsadmin_nonce: nonce
    });

    // Therefore we will use the PHP query args convention of passing hashes by using the names 'value[key]'
    //  as query args. IOK 2024-01-03
    for (const [key, value] of Object.entries(values)) {
      const phpkey: string = 'values[' + key + ']';
      if (value) params.append(phpkey, value);
      else params.append(phpkey, '');
    }

    const response = await fetch(getMetadata('admin_url')!, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      credentials: 'include',
      body: params.toString()
    });
    if (response.ok) {
      console.log('Response is %j', response);
    }
    return response.json();
  }

  return (
    <WPContext.Provider
      value={{
        setOption,
        getOption,
        hasOption,
        setOptions,
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
