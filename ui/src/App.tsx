import './App.css';
import { AdminSettings } from './components/admin-settings/admin-settings';
import { getMetadata } from './lib/wp-data';
import { WPOptionsProvider } from './wp-options-provider';

/**
 * Renders the main application component.
 *
 * @returns The rendered application component.
 */
function App(): JSX.Element {
  const isAdminSettingsPage = getMetadata('page') === 'admin_settings_page';
  return (
    <>
      {isAdminSettingsPage && (
        <WPOptionsProvider>
          <AdminSettings />
        </WPOptionsProvider>
      )}
    </>
  );
}

export default App;
