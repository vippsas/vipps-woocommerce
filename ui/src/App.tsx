import './App.css';
import { AdminSettings } from './components/admin-settings/admin-settings';
import { WPOptionsProvider } from './wp-options-provider';

function App() {
  return (
    <WPOptionsProvider>
      <AdminSettings />
    </WPOptionsProvider>
  );
}

export default App;
