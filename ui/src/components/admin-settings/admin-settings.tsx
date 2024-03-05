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
import { NotificationBanner } from '../notification-banner';

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
  const [error, setError] = useState<string>('');
  const { submitChanges, getOption, setOptions } = useWP();
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
    console.log("Handling save settings for %j", event.target);
    setIsLoading(true);

    let forceEnable : boolean = (event.target as Element).classList.contains('vippsWizard');

    try {
      const data = await submitChanges({forceEnable: forceEnable});
      // IOK FIXME Must separate notices and errors here 2024-01-03
      if (data.msg) {
          setError(data.msg);
      } 
      if (!data.ok) {
        setError(data.msg + "<br>Unsuccessful"); // IOK FIXME
      } else {
        // Ensure we have the new options, then reload the screens using the new values
        setOptions(data.options).then( () => 
            setShowWizardScreen(showWizardp())
        );
      }
    } catch (err) {
      console.log("An error happened");
      setError((err as Error).message);
    } finally {
      setIsLoading(false);
    }
  }

  function showWizardp () : boolean {
    const hasImportantSettings =
      getOption('merchantSerialNumber') && getOption('clientId') && getOption('secret') && getOption('Ocp_Apim_Key_eCommerce');
    const hasImportantSettingsTest =
      getOption('merchantSerialNumber_test') &&
      getOption('clientId_test') &&
      getOption('secret_test') &&
      getOption('Ocp_Apim_Key_eCommerce_test');
    return !hasImportantSettings && !hasImportantSettingsTest;
  }

  // If the most important settings are not set, the user is shown a screen to set these settings.
  // When they see this screen, they will not see the other settings (tabs, options).
  // When the important settings have been set, the user is shown the normal settings screen.
//  const [showWizardScreen] = useState(showWizardp);
  // IOK 2024-01-03 for demo purposes, always show wizard on first load. Real code above.
  const [showWizardScreen, setShowWizardScreen] = useState(()  => {
       console.log("iverok Loading wizard screen set to true");
       return true;
  });

  // Only show the wizard screen if the important settings are not set.
  if (showWizardScreen) {
    return <div>
      {error && <NotificationBanner variant="error">{error}</NotificationBanner>}
      <h3 className="vipps-mobilepay-react-tab-description">{gettext('initial_settings')}</h3>
      <WPForm onSubmit={handleSaveSettings} className="vippsAdminSettings vippsWizard">
       <AdminSettingsWizardScreenOptions />
       <WPButton variant="primary" disabled={isLoading}>
          {gettext('save_changes')} 1.4
       </WPButton>
      </WPForm>
      </div>;
  }

  // If the important settings are set, show the normal settings screen.
  return (
    <>
      {error && <NotificationBanner variant="error">{error}</NotificationBanner>}

      <Tabs tabs={TAB_IDS} onTabChange={setActiveTab} activeTab={activeTab} />
      <WPForm onSubmit={handleSaveSettings} className="vippsAdminSettings">
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

        <WPButton variant="primary" disabled={isLoading}>
          {gettext('save_changes')}
        </WPButton>
      </WPForm>
    </>
  );
}