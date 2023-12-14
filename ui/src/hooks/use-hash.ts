import { useCallback, useEffect, useState } from 'react';

/**
 * Custom hook that manages the hash portion of the URL.
 *
 * @param defaultValue - The default value for the hash.
 * @returns A tuple containing the current hash value and a function to update the hash.
 */
export function useHash(defaultValue?: string): [string, (newHash: string) => void] {
  const [hash, setHash] = useState(() => window.location.hash);

  const hashChangeHandler = useCallback(() => {
    setHash(window.location.hash);
  }, []);

  useEffect(() => {
    window.addEventListener('hashchange', hashChangeHandler);
    return () => {
      window.removeEventListener('hashchange', hashChangeHandler);
    };
  }, [hashChangeHandler]);

  const updateHash = useCallback(
    (newHash: string) => {
      if (newHash !== hash) window.location.hash = '#' + newHash;
    },
    [hash]
  );

  useEffect(() => {
    if (!hash && defaultValue) {
      updateHash(defaultValue);
    }
  }, [hash, defaultValue, updateHash]);

  return [decodeURIComponent(hash.replace('#', '')), updateHash];
}
