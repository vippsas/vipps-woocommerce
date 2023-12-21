import { useState } from 'react';
import { gettext } from '../../lib/wp-data';
import { WPButton, WPForm } from '../form-elements';
import { CheckboxFormField, InputFormField, SelectFormField } from '../options-form-fields';
import { useWP } from '../../wp-options-provider';

/**
 * A React component that renders the wizard screen options for the admin settings page.
 *
 * @returns The rendered wizard form fields.
 */
export function AdminSettingsWizardScreenOptions(): JSX.Element {
  const { submitChanges } = useWP();
  const [isLoading, setIsLoading] = useState(false);
  
  // Function to handle the save settings event.
  // This calls the submitChanges function from the WPOptionsProvider, which sends a request to the WordPress REST API to save the settings.
  async function handleSaveSettings(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsLoading(true);

    try {
      await submitChanges({ forceEnable: true });
      window.location.reload();
    } catch (error) {
      console.error(error);
    } finally {
      setIsLoading(false);
      window.location.reload();
    }
  }
  return (
    <div>
      <h3 className="vipps-mobilepay-react-tab-description">{gettext('initial_settings')}</h3>
      <WPForm onSubmit={handleSaveSettings}>
        {/* Renders a select field that specifies the payment method name (Vipps or MobilePay) */}
        <SelectFormField
          name="payment_method_name"
          titleKey="payment_method_name_title"
          descriptionKey="payment_method_name_label"
          options={[gettext('payment_method_name_options_vipps'), gettext('payment_method_name_options_mobilepay')]}
        />

        {/* Renders a checkbox to enable the Alternative screen  */}
        <CheckboxFormField
          name="vipps_checkout_enabled"
          titleKey="vipps_checkout_enabled_title"
          labelKey="vipps_checkout_enabled_label"
          descriptionKey="vipps_checkout_enabled_description"
        />

        {/* Renders an input field for the merchant serial number */}
        <InputFormField asterisk name="merchantSerialNumber" titleKey="merchantSerialNumber_title" labelKey="merchantSerialNumber_label" />

        {/* Renders an input field for the VippsMobilePay client ID */}
        <InputFormField asterisk name="clientId" titleKey="clientId_title" labelKey="clientId_label" />

        {/* Renders an input field for the VippsMobilePay secret */}
        <InputFormField asterisk name="secret" titleKey="secret_title" labelKey="secret_label" />

        {/* Renders an input field for the VippsMobilePay Ocp_Apim_Key_eCommerce */}
        <InputFormField name="Ocp_Apim_Key_eCommerce" titleKey="Ocp_Apim_Key_eCommerce_title" labelKey="Ocp_Apim_Key_eCommerce_label" />
        <WPButton variant="primary" isLoading={isLoading}>
          {gettext('save_changes')}
        </WPButton>
      </WPForm>
    </div>
  );
}
