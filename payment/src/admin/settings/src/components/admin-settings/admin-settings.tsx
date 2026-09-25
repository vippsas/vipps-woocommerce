import { gettext, getMetadata } from '../../lib/wp-data';
import { Tabs } from '../tabs';
import { AdminSettingsMainOptionsTab } from './main-options-tab';
import { AdminSettingsCCOptionsTab } from './cc-options-tab.tsx';
import { AdminSettingsExpressOptionsTab } from './express-options-tab';
import { AdminSettingsCheckoutOptionsTab } from './checkout-options-tab';
import { AdminSettingsAdvancedOptionsTab } from './advanced-options-tab';
import { AdminSettingsKeysOptionsTab } from './keys-options-tab.tsx';
import { useHash } from '../../hooks/use-hash';
import { truthToBool, WPButton, WPForm } from '../form-elements';
import { useWP } from '../../wp-options-provider';
import { useState } from 'react';
import { AdminSettingsWizardScreenOptions } from './wizard-screen-options';
import { NotificationBanner, type NotificationBannerProps } from '../notification-banner';
import { isPaymentMethodCurrencySupported, getPaymentMethodSupportedCurrencies } from '../../lib/payment-method';

// Development option to force the wizard screen to be shown. This is useful for testing the wizard screen.
// can also be set as the constant 'WOO_VIPPS_FORCE_WIZARD' in wp-config.php IOK 2025-01-20
const __DEV_FORCE_WIZARD_SCREEN = false;

/**
 * A React component that renders the admin settings page.
 *
 * @returns The rendered admin settings page.
 */
export function AdminSettings(): JSX.Element {
  const [isLoading, setIsLoading] = useState(false);
  const [banner, setBanner] = useState<NotificationBannerProps | null>();
  const [saveConfirmation, setSaveConfirmation] = useState(false);
  const { submitChanges, getOption, setOptions } = useWP();
  const currency = getMetadata('currency');  // Get currency from metadata
  const companyName = getMetadata('company_name') ?? 'Vipps MobilePay';
  const paymentMethod = getOption('payment_method_name');
  const showCurrencyWarning = !isPaymentMethodCurrencySupported(paymentMethod, currency);
  // The tabs to render on the admin settings page.

  const MAIN_TAB_ID = gettext('main_options.title');
  const EXPRESS_TAB_ID = gettext('express_options.title');
  const CC_TAB_ID =  gettext('cc_options.title');
  const CHECKOUT_TAB_ID = gettext('checkout_options.title');
  const KEYS_TAB_ID = gettext('keys_options.title');
  const ADVANCED_TAB_ID = gettext('advanced_options.title');

  // Maps tab id to tab priority
  // Priority is how we order the tabs, lower means first (from the left). LP 2026-09-25
  const TAB_PRIORITIES: Record<string, number> = {
    [MAIN_TAB_ID]: 0,
    [EXPRESS_TAB_ID]: 1,
    [CC_TAB_ID]: 2,
    [KEYS_TAB_ID]: 4,
    [ADVANCED_TAB_ID]: 5,
  };

  // Returns tabs from lowest to highest prio. LP 2026-09-25
  const getOrderedTabIds = (): string[] =>
    Object.keys(TAB_PRIORITIES).sort((a, b) => TAB_PRIORITIES[a] - TAB_PRIORITIES[b]);

  // Only show checkout options if known to be active (option woo_vipps_checkout_activated is true, or the vipps_checkout_enabled option is yes IOK 2026-04-30
  const checkoutActive = +(getMetadata('vipps_checkout_activated') ?? 0) || getOption('vipps_checkout_enabled') == 'yes';
  if (checkoutActive) {
    TAB_PRIORITIES[CHECKOUT_TAB_ID] = 3;
  }

  // For debugging: show wizard screen if option is set in wp-config. IOK 2025-10-20
  const force_override = getMetadata('__dev_force_wizard_screen') || "";
  const force_wizard_screen =  __DEV_FORCE_WIZARD_SCREEN || ["1", "yes", "true", "TRUE"].includes(force_override);

  // Get the active tab from the URL hash.
  const [activeTab, setActiveTab] = useHash(MAIN_TAB_ID);

  // Function to determine if a tab is visible.
  function isVisible(tab: string): boolean {
    return tab === selectedTab;
  }

  // Function to handle the save settings event.
  // This calls the submitChanges function from the WPOptionsProvider, which sends a request to the WordPress REST API to save the settings.
  async function handleSaveSettings(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsLoading(true);
    setSaveConfirmation(false);

    try {
      const data = await submitChanges({ forceEnable: showWizardScreen });
      console.log('handleSaveSettings - Response data:', data);

      // Handle the error messages for connection and form errors
      if (!data.connection_ok || !data.form_ok) {
        setBanner({
          text: data.connection_msg || data.form_errors,
          variant: 'error'
        });
        setSaveConfirmation(false);
      } else {
        // If the connection is ok, show a success message
        setBanner({
          text: data.connection_msg,
          variant: 'success'
        });
        setSaveConfirmation(true);
        console.log('handleSaveSettings - Setting new options:', data.options);
        // Ensure we have the new options, then reload the screens using the new values
        await setOptions(data.options);
        setShowWizardScreen(showWizardp());

        // Auto-hide save confirmation after 2 seconds
        setTimeout(() => {
          setSaveConfirmation(false);
        }, 2000);
      }
    } catch (err) {
      console.error('handleSaveSettings - Error:', err);
      setBanner({
        text: (err as Error).message,
        variant: 'error'
      });
      setSaveConfirmation(false);
    } finally {
      setIsLoading(false);
    }
  }

  function showWizardp(): boolean {
    const hasImportantSettings =
      getOption('merchantSerialNumber') &&
      getOption('clientId') &&
      getOption('secret') &&
      getOption('Ocp_Apim_Key_eCommerce') &&
      getOption('country') &&
      getOption('payment_method_name');
    
    const isTestMode = truthToBool(getOption('testmode'));

    const hasImportantSettingsTest =
      getOption('merchantSerialNumber_test') &&
      getOption('clientId_test') &&
      getOption('secret_test') &&
      getOption('Ocp_Apim_Key_eCommerce_test') &&
      getOption('payment_method_name') &&
      getOption('country');
    return !hasImportantSettings && !hasImportantSettingsTest && !isTestMode;
  }

  // If the most important settings are not set, the user is shown a screen to set these settings.
  // When they see this screen, they will not see the other settings (tabs, options).
  // When the important settings have been set, the user is shown the normal settings screen.
  const [showWizardScreen, setShowWizardScreen] = useState(() => force_wizard_screen || showWizardp());

  // If main gw is disabled, only show keys tab. LP 2026-09-25
  const gwEnabled = truthToBool(getOption('enabled'));
  const visibleTabs = gwEnabled ? getOrderedTabIds() : [MAIN_TAB_ID, KEYS_TAB_ID];

  const selectedTab = visibleTabs.includes(activeTab) ? activeTab : MAIN_TAB_ID;

  return (
    <div className="vipps-settings-shell">
      <header className="vipps-settings-header">
        <h1>{companyName}</h1>
      </header>

      {(banner || showCurrencyWarning) && (
        <div className="vipps-settings-notices" role="status">
          {banner && <NotificationBanner variant={banner.variant} text={banner.text} />}
          {showCurrencyWarning && (
            <NotificationBanner
              variant="error"
              text={`${paymentMethod} does not support your store currency (${currency}). Supported currencies: ${getPaymentMethodSupportedCurrencies(paymentMethod).join(', ')}`}
            />
          )}
        </div>
      )}

      <WPForm onSubmit={handleSaveSettings} className="vippsAdminSettings">
        {showWizardScreen ? (
          // If the important settings are not set, show the wizard screen.
          <div className="vipps-settings-panel vipps-settings-panel-wizard">
            <AdminSettingsWizardScreenOptions isLoading={isLoading} />
          </div>
        ) : (
          // If the important settings are set, show the normal settings screen.
          <div className="vipps-settings-layout">
            <nav className="vipps-settings-navigation" aria-label={gettext('main_options.title')}>
              <Tabs tabs={visibleTabs} onTabChange={setActiveTab} activeTab={selectedTab} />
            </nav>

            <div className="vipps-settings-content">
              <section
                className="vipps-settings-panel"
                id="vipps-settings-tab-panel"
                role="tabpanel"
                aria-labelledby={`vipps-settings-tab-${visibleTabs.indexOf(selectedTab)}`}
                tabIndex={0}
              >
                {isVisible(MAIN_TAB_ID) && <AdminSettingsMainOptionsTab />}
                {isVisible(EXPRESS_TAB_ID) && <AdminSettingsExpressOptionsTab />}
                {isVisible(CC_TAB_ID) && <AdminSettingsCCOptionsTab />}
                {checkoutActive && isVisible(CHECKOUT_TAB_ID) && <AdminSettingsCheckoutOptionsTab />}
                {isVisible(KEYS_TAB_ID) && <AdminSettingsKeysOptionsTab />}
                {isVisible(ADVANCED_TAB_ID) && <AdminSettingsAdvancedOptionsTab />}
              </section>

              <div className="vipps-mobilepay-react-save-section">
                {saveConfirmation && (
                  <span className="vipps-mobilepay-react-save-confirmation" role="status">
                    <span className="dashicons dashicons-yes" aria-hidden="true"></span>
                    {gettext('settings_saved')}
                  </span>
                )}
                <WPButton variant="primary" isLoading={isLoading}>
                  {gettext('save_changes')}
                </WPButton>
              </div>
            </div>
          </div>
        )}
      </WPForm>
    </div>
  );
}
