import { useCallback, useEffect, useState } from 'react';

/**
 * Custom hook that provides functionality for managing and updating the hash portion of the window location.
 * @param defaultValue The default value for the hash.
 * @returns A tuple containing the current hash value and a function to update the hash.
 */
export function useHash(defaultValue?: string): [string, (newHash: string) => void] {
  const [hash, setHash] = useState(() => window.location.hash);

  /**
   * Callback function that handles the change in the hash of the window location.
   */
  const hashChangeHandler = useCallback(() => {
    setHash(window.location.hash);
  }, []);

  /**
   * Add an event listener to the window to listen for changes in the hash.
   *
   * @note The event listener is removed when the component unmounts.
   */
  useEffect(() => {
    window.addEventListener('hashchange', hashChangeHandler);
    return () => {
      window.removeEventListener('hashchange', hashChangeHandler);
    };
  }, [hashChangeHandler]);

  /**
   * Function that updates the hash of the window location.
   * @param newHash The new hash value.
   */
  const updateHash = useCallback(
    (newHash: string) => {
      if (newHash !== hash) window.location.hash = '#' + newHash;
    },
    [hash]
  );

  /**
   * If the hash is empty and a default value is provided, update the hash.
   */
  useEffect(() => {
    if (!hash && defaultValue) {
      updateHash(defaultValue);
    }
  }, [hash, defaultValue, updateHash]);

  /**
   * Parse the hash and return the parsed value and the update function.
   */
  const parsedHash = decodeURIComponent(hash.replace('#', ''));
  return [parsedHash, updateHash];
}
