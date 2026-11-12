import './App.css';
import './recurring.css';
import { AdminSettings } from './components/admin-settings';
import { WPOptionsProvider } from './wp-options-provider';

/** Recurring owns this screen and its state; only its visual components mirror Payment. */
export default function App(): JSX.Element {
  return <div className="vipps-mobilepay-react-admin-page vipps-recurring-react-admin-page">
    <WPOptionsProvider><AdminSettings /></WPOptionsProvider>
  </div>;
}
