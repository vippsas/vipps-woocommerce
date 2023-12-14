import { PropsWithChildren, createContext, useContext, useState } from 'react';
import { VippsMobilePayReactOptions } from './lib/wp-data';

interface WPContext {
  setOption: (option: string, value: string | null) => Promise<void>;
  getOption: (option: string) => string;
  submitChanges: (event: React.FormEvent<HTMLFormElement>) => Promise<void>;
}
const WPContext = createContext<WPContext>(null!);

export function WPOptionsProvider({ children }: PropsWithChildren) {
  const [values, setValues] = useState<Record<string, string | null>>(VippsMobilePayReactOptions);

  function getOption(key: string): string {
    return values?.[key] ?? '';
  }

  async function setOption(key: string, value: string | null) {
    setValues((values) => ({
      ...values,
      [key]: value ?? null
    }));
  }

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

export function useWP() {
  const context = useContext(WPContext);
  if (!context) {
    throw new Error('useWP must be used within a WPOptionsProvider');
  }
  return context;
}
