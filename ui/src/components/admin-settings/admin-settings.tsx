import { gettext } from '../../lib/wp-data';
import { Tabs } from '../tabs';
import { AdminSettingsMainOptionsTab } from './main-options-tab';
import { AdminSettingsExpressOptionsTab } from './express-options-tab';
import { AdminSettingsCheckoutOptionsTab } from './checkout-options-tab';
import { AdminSettingsAdvancedOptionsTab } from './advanced-options-tab';
import { useHash } from '../../hooks/use-hash';
import { AdminSettingsDeveloperOptionsTab } from './developer-options-tab';

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
  // Get the active tab from the URL hash.
  const [activeTab, setActiveTab] = useHash(TAB_IDS[0]);

  // Function to determine if a tab is visible.
  function isVisible(tab: string): boolean {
    return tab !== activeTab;
  }

  return (
    <>
      <Tabs tabs={TAB_IDS} onTabChange={setActiveTab} activeTab={decodeURIComponent(activeTab)} />
      <div
        style={{
          display: isVisible(TAB_IDS[0]) ? 'none' : 'block'
        }}
      >
        <AdminSettingsMainOptionsTab />
      </div>
      <div
        style={{
          display: isVisible(TAB_IDS[1]) ? 'none' : 'block'
        }}
      >
        <AdminSettingsExpressOptionsTab />
      </div>
      <div
        style={{
          display: isVisible(TAB_IDS[2]) ? 'none' : 'block'
        }}
      >
        <AdminSettingsCheckoutOptionsTab />
      </div>
      <div
        style={{
          display: isVisible(TAB_IDS[3]) ? 'none' : 'block'
        }}
      >
        <AdminSettingsAdvancedOptionsTab />
      </div>
      <div
        style={{
          display: isVisible(TAB_IDS[4]) ? 'none' : 'block'
        }}
      >
        <AdminSettingsDeveloperOptionsTab />
      </div>
    </>
  );
}
