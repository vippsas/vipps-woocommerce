import { PropsWithChildren, createContext, useContext, useState } from 'react';
import { VippsMobilePayReactOptions } from './lib/wp-data';

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
   * @param event - The form event triggering the submission.
   * @returns A promise that resolves when the changes are submitted.
   */
  submitChanges: (event: React.FormEvent<HTMLFormElement>) => Promise<void>;
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
  async function submitChanges(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const changedOptions = Object.entries(values).filter(([key, value]) => VippsMobilePayReactOptions[key] !== value);

    for (const [key, value] of changedOptions) {
      try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            action: 'set_option',
            key,
            value
          })
        });
        if (!response.ok) {
          throw new Error(response.statusText);
        }
      } catch (error) {
        console.error(error);
        throw error;
      }
    }
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
