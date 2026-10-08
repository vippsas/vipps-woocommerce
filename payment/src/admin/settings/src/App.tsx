import './App.css';
import { AdminSettings } from './components/admin-settings/admin-settings';
import { getMetadata } from './lib/wp-data';
import { WPOptionsProvider } from './wp-options-provider';
import { ButtonsPage } from './components/extra-pages/buttons-page';
import { BadgesPage } from './components/extra-pages/badges-page';
import { WebhooksPage } from './components/extra-pages/webhooks-page';

/**
 * Renders the main application component.
 *
 * @returns The rendered application component.
 */
function App(): JSX.Element {
  const isAdminSettingsPage = getMetadata('page') === 'admin_settings_page';
  const page = getMetadata('page');
  return (
    <div className='vipps-mobilepay-react-admin-page'>
      {isAdminSettingsPage && (
        <WPOptionsProvider>
          <AdminSettings />
        </WPOptionsProvider>
      )}
      {page === 'buttons' && <ButtonsPage />}
      {page === 'badges' && <BadgesPage />}
      {page === 'webhooks' && <WebhooksPage />}
    </div>
  );
}

export default App;
