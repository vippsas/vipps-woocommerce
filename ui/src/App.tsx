import './App.css';
import { AdminSettings } from './components/admin-settings/admin-settings';
import { WPOptionsProvider } from './wp-options-provider';

/**
 * Renders the main application component.
 *
 * @returns The rendered application component.
 */
function App(): JSX.Element {
  return (
    <WPOptionsProvider>
      <AdminSettings />
    </WPOptionsProvider>
  );
}

export default App;
