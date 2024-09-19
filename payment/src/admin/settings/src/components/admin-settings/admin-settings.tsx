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
import { NotificationBanner, type NotificationBannerProps } from '../notification-banner';

// Development option to force the wizard screen to be shown. This is useful for testing the wizard screen.
const __DEV_FORCE_WIZARD_SCREEN = false;

/**
 * A React component that renders the admin settings page.
 *
 * @returns The rendered admin settings page.
 */
export function AdminSettings(): JSX.Element {
  const [isLoading, setIsLoading] = useState(false);
  const [banner, setBanner] = useState<NotificationBannerProps | null>();
  const { submitChanges, getOption, setOptions } = useWP();
  // The tabs to render on the admin settings page.
  const TAB_IDS = [
    gettext('main_options.title'),
    gettext('express_options.title'),
    gettext('checkout_options.title'),
    gettext('advanced_options.title')
  ];
  // If the developer mode is enabled, the developer options tab is shown.
  const canShowDeveloperOptions = getOption('developermode') === 'yes';
  if (canShowDeveloperOptions) {
    TAB_IDS.push(gettext('developertitle.title'));
  }

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
      const data = await submitChanges({ forceEnable: showWizardScreen });

      // Handle the error messages for connection and form errors
      if (!data.connection_ok || !data.form_ok) {
        setBanner({
          text: data.connection_msg || data.form_errors,
          variant: 'error'
        });
      } else {
        // If the connection is ok, show a success message
        setBanner({
          text: data.connection_msg,
          variant: 'success'
        });
        // Ensure we have the new options, then reload the screens using the new values
        setOptions(data.options).then(() => setShowWizardScreen(showWizardp()));
      }
    } catch (err) {
      setBanner({
        text: (err as Error).message,
        variant: 'error'
      });
    } finally {
      setIsLoading(false);
    }
  }

  function showWizardp(): boolean {
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
  const [showWizardScreen, setShowWizardScreen] = useState(() => __DEV_FORCE_WIZARD_SCREEN || showWizardp());

  return (
    <>
      {banner && <NotificationBanner variant={banner.variant} text={banner.text} />}

      <WPForm onSubmit={handleSaveSettings} className="vippsAdminSettings">
        {showWizardScreen ? (
          // If the important settings are not set, show the wizard screen.
          <AdminSettingsWizardScreenOptions isLoading={isLoading} />
        ) : (
          // If the important settings are set, show the normal settings screen.
          <>
            <Tabs tabs={TAB_IDS} onTabChange={setActiveTab} activeTab={activeTab} />
            {/* Renders the main options form fields  */}
            {isVisible(TAB_IDS[0]) && <AdminSettingsMainOptionsTab />}

            {/* Renders the express options form fields */}
            {isVisible(TAB_IDS[1]) && <AdminSettingsExpressOptionsTab />}

            {/* Renders the checkout options form fields */}
            {isVisible(TAB_IDS[2]) && <AdminSettingsCheckoutOptionsTab />}

            {/* Renders the advanced options form fields */}
            {isVisible(TAB_IDS[3]) && <AdminSettingsAdvancedOptionsTab />}

            {/* Renders the developer options form fields */}
            {canShowDeveloperOptions && isVisible(TAB_IDS[4]) && <AdminSettingsDeveloperOptionsTab />}
            <WPButton variant="primary" isLoading={isLoading}>
              {gettext('save_changes')}
            </WPButton>
          </>
        )}
      </WPForm>
    </>
  );
}
