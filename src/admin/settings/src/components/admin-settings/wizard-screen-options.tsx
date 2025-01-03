//import { useState } from 'react';
import { useState } from 'react';
import { gettext } from '../../lib/wp-data';
//import { WPButton, WPForm } from '../form-elements';
import { CheckboxFormField, InputFormField, SelectFormField } from '../options-form-fields';
import { WPButton } from '../form-elements';
import { useWP } from '../../wp-options-provider';
import { detectPaymentMethodName } from '../../lib/payment-method';
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
  const { setOption } = useWP();
  const [step, setStep] = useState<'ESSENTIAL' | 'CHECKOUT'>('ESSENTIAL');

  return (
    <>
      <h3 className="vipps-mobilepay-react-tab-description">{gettext('initial_settings')}</h3>

      {step === 'ESSENTIAL' && (
        <>
          <SelectFormField
            name="country"
            titleKey="country.title"
            descriptionKey="country.description"
            onChange={(e) => {
              // Set the payment method name based on the selected country
              const paymentMethod = detectPaymentMethodName(e.target.value);
              setOption('payment_method_name', paymentMethod);
            }}
            required
            includeEmptyOption={false}
            options={[
              { label: gettext('country.options.NO'), value: 'NO' },
              { label: gettext('country.options.SE'), value: 'SE' },
              { label: gettext('country.options.FI'), value: 'FI' },
              { label: gettext('country.options.DK'), value: 'DK' }
            ]}
          />

          {/* Renders a select field that specifies the payment method name (Vipps or MobilePay) */}
          <SelectFormField
            name="payment_method_name"
            titleKey="payment_method_name.title"
            descriptionKey="payment_method_name.description"
            required
            includeEmptyOption={false}
            options={[
              {
                label: gettext('payment_method_name.options.Vipps'),
                value: 'Vipps'
              },
              {
                label: gettext('payment_method_name.options.MobilePay'),
                value: 'MobilePay'
              }
            ]}
          />

          {/* Renders an input field for the merchant serial number */}
          <InputFormField
            asterisk
            name="merchantSerialNumber"
            titleKey="merchantSerialNumber.title"
            descriptionKey="merchantSerialNumber.description"
            required
          />

          {/* Renders an input field for the VippsMobilePay client ID */}
          <InputFormField asterisk name="clientId" titleKey="clientId.title" descriptionKey="clientId.description" required />

          {/* Renders an input field for the VippsMobilePay secret */}
          <InputFormField asterisk name="secret" titleKey="secret.title" descriptionKey="secret.description" required />

          {/* Renders an input field for the VippsMobilePay Ocp_Apim_Key_eCommerce */}
          <InputFormField
            asterisk
            name="Ocp_Apim_Key_eCommerce"
            titleKey="Ocp_Apim_Key_eCommerce.title"
            descriptionKey="Ocp_Apim_Key_eCommerce.description"
            required
          />
          <WPButton
            variant="primary"
            type="button"
            onClick={(e) => {
              e.preventDefault();
              // Ensure there's always a form so we can trigger validation
              const form = e.currentTarget.closest('form') as HTMLFormElement | null;
              if (!form) throw new Error('Form not found');

              // Trigger validation and proceed to the next step if valid
              if (form.reportValidity()) {
                setStep('CHECKOUT');
              }
            }}
          >
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
