//import { useState } from 'react';
import { useState } from 'react';
import { gettext } from '../../lib/wp-data';
//import { WPButton, WPForm } from '../form-elements';
import { CheckboxFormField, InputFormField, SelectFormField } from '../options-form-fields';
import { WPButton } from '../form-elements';
//import { useWP } from '../../wp-options-provider';

/**
 * A React component that renders the wizard screen options for the admin settings page.
 *
 * @returns The rendered wizard form fields.
 */
interface Props {
  isLoading: boolean;
}

export function AdminSettingsWizardScreenOptions({ isLoading }: Props): JSX.Element {
  const [step, setStep] = useState<'ESSENTIAL' | 'CHECKOUT'>('ESSENTIAL');
  return (
    <>
      <h3 className="vipps-mobilepay-react-tab-description">{gettext('initial_settings')}</h3>

      {step === 'ESSENTIAL' && (
        <>
          {/* Renders a select field that specifies the payment method name (Vipps or MobilePay) */}
          <SelectFormField
            name="payment_method_name"
            titleKey="payment_method_name.title"
            descriptionKey="payment_method_name.description"
            options={[
              { label: gettext('payment_method_name.options.Vipps'), value: 'Vipps' },
              { label: gettext('payment_method_name.options.MobilePay'), value: 'MobilePay' }
            ]}
          />

          {/* Renders an input field for the merchant serial number */}
          <InputFormField
            asterisk
            name="merchantSerialNumber"
            titleKey="merchantSerialNumber.title"
            descriptionKey="merchantSerialNumber.description"
          />

          {/* Renders an input field for the VippsMobilePay client ID */}
          <InputFormField asterisk name="clientId" titleKey="clientId.title" descriptionKey="clientId.description" />

          {/* Renders an input field for the VippsMobilePay secret */}
          <InputFormField asterisk name="secret" titleKey="secret.title" descriptionKey="secret.description" />

          {/* Renders an input field for the VippsMobilePay Ocp_Apim_Key_eCommerce */}
          <InputFormField
            asterisk
            name="Ocp_Apim_Key_eCommerce"
            titleKey="Ocp_Apim_Key_eCommerce.title"
            descriptionKey="Ocp_Apim_Key_eCommerce.description"
          />
          <WPButton variant="primary" type="button" onClick={() => setStep('CHECKOUT')}>
            {gettext('next_step')}
          </WPButton>
        </>
      )}

      {step === 'CHECKOUT' && (
        <>
          {/* Renders a checkbox to enable the Alternative screen  */}
          <CheckboxFormField
            name="vipps_checkout_enabled"
            titleKey="vipps_checkout_enabled.title"
            labelKey="vipps_checkout_enabled.label"
            descriptionKey="vipps_checkout_enabled.description"
          />
          <div className="vipps-mobilepay-react-button-actions">
            <WPButton variant="secondary" type="button" onClick={() => setStep('ESSENTIAL')}>
              {gettext('previous_step')}
            </WPButton>
            <WPButton variant="primary" isLoading={isLoading}>
              {gettext('save_changes')}
            </WPButton>
          </div>
        </>
      )}
    </>
  );
}
