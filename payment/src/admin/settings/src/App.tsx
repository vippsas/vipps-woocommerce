import './App.css';
import { AdminSettings } from './components/settings-menu/admin-settings';
import { getMetadata } from './lib/wp-data';
import { WPOptionsProvider } from './wp-options-provider';
import { ButtonsPage } from './components/buttons-menu/buttons-page';
import { BadgesPage } from './components/badges-menu/badges-page';
import { WebhooksPage } from './components/webhooks-menu/webhooks-page';

/**
 * Renders the main application component.
 *
 * @returns The rendered application component.
 */
function App(): JSX.Element {
  const page = getMetadata('page');
  return (
    <div className='vipps-mobilepay-react-admin-page'>
      {page === 'admin_settings_page' && (
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
