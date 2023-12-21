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
import { AdminSettingsWizardScreenOptions } from './wizard-screen-options';

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
  const { submitChanges, getOption } = useWP();
  // Get the active tab from the URL hash.
  const [activeTab, setActiveTab] = useHash(TAB_IDS[0]);

  // Function to determine if a tab is visible.
  function isVisible(tab: string): boolean {
    return tab === activeTab;
  }

  // Function to handle the save settings event.
  // This calls the submitChanges function from the WPOptionsProvider, which sends a request to the WordPress REST API to save the settings.
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

  // If the most important settings are not set, the user is shown a screen to set these settings.
  // When they see this screen, they will not see the other settings (tabs, options).
  // When the important settings have been set, the user is shown the normal settings screen.
  const [showWizardScreen] = useState(() => {
    // This must be a useState hook because we only want to run this check on the first render.
    // Otherwise, the wizard screen would be shown every time the user changes a setting.
    return (
      (!getOption('merchantSerialNumber') && !getOption('merchantSerialNumber_test')) ||
      (!getOption('clientId') && !getOption('clientId_test')) ||
      (!getOption('secret') && !getOption('secret_test')) ||
      (!getOption('Ocp_Apim_Key_eCommerce') && !getOption('Ocp_Apim_Key_eCommerce_test'))
    );
  });

  // Only show the wizard screen if the important settings are not set.
  if (showWizardScreen) {
    return <AdminSettingsWizardScreenOptions />;
  }

  // If the important settings are set, show the normal settings screen.
  return (
    <>
      <Tabs tabs={TAB_IDS} onTabChange={setActiveTab} activeTab={activeTab} />
      <WPForm onSubmit={handleSaveSettings}>
        {/* Renders the main options form fields  */}
        {isVisible(TAB_IDS[0]) && <AdminSettingsMainOptionsTab />}

        {/* Renders the express options form fields */}
        {isVisible(TAB_IDS[1]) && <AdminSettingsExpressOptionsTab />}

        {/* Renders the checkout options form fields */}
        {isVisible(TAB_IDS[2]) && <AdminSettingsCheckoutOptionsTab />}

        {/* Renders the advanced options form fields */}
        {isVisible(TAB_IDS[3]) && <AdminSettingsAdvancedOptionsTab />}

        {/* Renders the developer options form fields */}
        {isVisible(TAB_IDS[4]) && <AdminSettingsDeveloperOptionsTab />}

        <WPButton variant="primary" isLoading={isLoading}>
          {gettext('save_changes')}
        </WPButton>
      </WPForm>
    </>
  );
}
