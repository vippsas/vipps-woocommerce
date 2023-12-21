import { gettext } from '../../lib/wp-data';
import { Tabs } from '../tabs';
import { AdminSettingsMainOptionsTab } from './main-options-tab';
import { AdminSettingsExpressOptionsTab } from './express-options-tab';
import { AdminSettingsCheckoutOptionsTab } from './checkout-options-tab';
import { AdminSettingsAdvancedOptionsTab } from './advanced-options-tab';
import { useHash } from '../../hooks/use-hash';
import { AdminSettingsDeveloperOptionsTab } from './developer-options-tab';
import { WPButton, WPForm } from '../form-elements';
import { useWP } from '../../wp-options-provider';
import { useState } from 'react';

// The tabs to render on the admin settings page.
const TAB_IDS = [
  gettext('main_options_title'),
  gettext('express_options_title'),
  gettext('checkout_options_title'),
  gettext('advanced_options_title'),
  gettext('developer_options_title')
];

/**
 * A React component that renders the admin settings page.
 *
 * @returns The rendered admin settings page.
 */
export function AdminSettings(): JSX.Element {
  const [isLoading, setIsLoading] = useState(false);
  const { submitChanges } = useWP();
  // Get the active tab from the URL hash.
  const [activeTab, setActiveTab] = useHash(TAB_IDS[0]);

  // Function to determine if a tab is visible.
  function isVisible(tab: string): boolean {
    return tab === activeTab;
  }

  async function handleSaveSettings(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsLoading(true);

    try {
      await submitChanges();
      window.location.reload();
    } catch (error) {
      console.error(error);
    } finally {
      setIsLoading(false);
      window.location.reload();
    }
  }

  return (
    <>
      <Tabs tabs={TAB_IDS} onTabChange={setActiveTab} activeTab={decodeURIComponent(activeTab)} />

      <WPForm onSubmit={handleSaveSettings}>
        {isVisible(TAB_IDS[0]) && <AdminSettingsMainOptionsTab />}
        {isVisible(TAB_IDS[1]) && <AdminSettingsExpressOptionsTab />}
        {isVisible(TAB_IDS[2]) && <AdminSettingsCheckoutOptionsTab />}
        {isVisible(TAB_IDS[3]) && <AdminSettingsAdvancedOptionsTab />}
        {isVisible(TAB_IDS[4]) && <AdminSettingsDeveloperOptionsTab />}

        <WPButton variant="primary" isLoading={isLoading}>
          {gettext('save_changes')}
        </WPButton>
      </WPForm>
    </>
  );
}
