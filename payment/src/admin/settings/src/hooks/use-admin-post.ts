import { useRef, useState } from 'react';
import { gettext } from '../lib/wp-data';
import { NotificationBannerProps } from '../components/notification-banner';

type Result<T> = { success: boolean; data: T & { msg: string } };

// Submit the existing admin-post form, including its action and nonce.
export function useAdminPost() {
  const busy = useRef(false);
  const [pending, setPending] = useState<string | null>(null);
  const [banner, setBanner] = useState<NotificationBannerProps | null>(null);

  async function submit<T>(form: HTMLFormElement, key = 'save'): Promise<T | null> {
    if (busy.current) return null;
    busy.current = true;
    setPending(key);
    setBanner(null);
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        body: new FormData(form),
      });
      let result: Result<T>;
      try {
        result = await response.json();
      } catch {
        throw new Error(gettext('request_error'));
      }
      if (!response.ok || !result?.success) {
        throw new Error(result?.data?.msg || gettext('request_error'));
      }
      setBanner({ variant: 'success', text: result.data.msg });
      return result.data;
    } catch (error) {
      setBanner({ variant: 'error', text: error instanceof Error ? error.message : gettext('request_error') });
      return null;
    } finally {
      busy.current = false;
      setPending(null);
    }
  }

  return { submit, pending, banner, clearBanner: () => setBanner(null) };
}
