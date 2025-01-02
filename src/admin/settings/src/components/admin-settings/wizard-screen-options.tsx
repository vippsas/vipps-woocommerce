import { useState } from 'react';
import { gettext } from '../../lib/wp-data';
import { CheckboxFormField, InputFormField, SelectFormField } from '../options-form-fields';
import { WPButton, WPFormField, WPLabel, boolToTruth, truthToBool } from '../form-elements';
import { useWP } from '../../wp-options-provider';
import { detectPaymentMethodName } from '../../lib/payment-method';
import { UnsafeHtmlText } from '../unsafe-html-text';

/**
 * A React component that renders the wizard screen options for the admin settings page.
 *
 * @returns The rendered wizard form fields.
 */
interface Props {
  isLoading: boolean;
}

export function AdminSettingsWizardScreenOptions({ isLoading }: Props): JSX.Element {
  const { getOption, setOption } = useWP();
  const [step, setStep] = useState<'ESSENTIAL' | 'CHECKOUT_CONFIRM' | 'CHECKOUT'>('ESSENTIAL');
  return (
    <>
      <h3 className="vipps-mobilepay-react-tab-description">{gettext('initial_settings')}</h3>

      {step === 'ESSENTIAL' && (
        <>
          <div className="vipps-mobilepay-form-container">
            <div className="vipps-mobilepay-form-col">
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

              {/* Renders a simplified checkbox to enable the Alternative checkout screen. LP 23.12.2024 */}
              <CheckboxFormField
                name="vipps_checkout_enabled"
                titleKey="vipps_checkout_enabled_simple.title"
                labelKey="vipps_checkout_enabled_simple.label"
                descriptionKey="vipps_checkout_enabled_simple.description"
              />

              {/* Renders a button to progress to the next form step. LP 23.12.2024 */}
              <WPFormField>
                <WPLabel></WPLabel>
                <div className="vipps-mobilepay-react-col">
                  <WPButton
                    style={{ alignSelf: "flex-start" }}
                    variant="primary"
                    type="button"
                    onClick={(e) => {
                      e.preventDefault();
                      // Ensure there's always a form so we can trigger validation
                      const form = e.currentTarget.closest(
                        "form"
                      ) as HTMLFormElement | null;
                      if (!form) throw new Error("Form not found");

                      // Trigger validation and proceed to the next step if valid
                      if (form.reportValidity()) {
                        // If user did not enable checkout - show confirmation step. LP 30.12.2024
                        if (truthToBool(getOption('vipps_checkout_enabled'))) setStep('CHECKOUT');
                        else setStep('CHECKOUT_CONFIRM');
                      }
                    }}
                  >
                    {gettext("next_step")}
                  </WPButton>
                </div>
              </WPFormField>
            </div>

            {/* Help box on the right hand side. LP 23.12.2024*/}
            <div className="vipps-mobilepay-form-col vipps-mobilepay-form-help-box">
              <div>
                <strong className="title">{gettext('help_box.get_started')}</strong><br/>
                <a href="https://wordpress.org/plugins/woo-vipps/">{gettext('help_box.documentation')}</a><br/>
                <a href="https://portal.vippsmobilepay.com">{gettext('help_box.portal')}</a>
              </div>
              <br/>
              <div>
                <strong className="title">{gettext('help_box.support.title')}</strong><br/>
                <UnsafeHtmlText htmlString={gettext('help_box.support.description')}/>
              </div>
            </div>
          </div>
        </>
      )}

      {step === 'CHECKOUT_CONFIRM' && (
        <>
        <div className="vipps-mobilepay-react-checkout-confirm">
          <h1 className="title"><strong>{getOption('payment_method_name') === "Vipps" ? gettext('checkout_confirm.title.vipps') : gettext('checkout_confirm.title.mobilepay')}</strong></h1>
          <div className="body">
            <div className="list">
              <strong>{getOption('payment_method_name') === "Vipps" ? gettext('checkout_confirm.paragraph1.header.vipps') : gettext('checkout_confirm.paragraph1.header.mobilepay')}</strong>
              <ul>
                <li>{gettext('checkout_confirm.paragraph1.first')}</li>
                <li>{gettext('checkout_confirm.paragraph1.second')}</li>
                <li>{gettext('checkout_confirm.paragraph1.third')}</li>
              </ul>
              <strong>{gettext('checkout_confirm.paragraph2.header')}</strong>
              <ul>
                <li>{gettext('checkout_confirm.paragraph2.first')}</li>
                <li>{gettext('checkout_confirm.paragraph2.second')}</li>
              </ul>
            </div>
            <img src={getOption("payment_method_name") == "Vipps" ? gettext('checkout_confirm.img.vipps.src') : gettext('checkout_confirm.img.mobilepay.src')} alt={getOption("payment_method_name") == "Vipps" ? gettext('checkout_confirm.img.vipps.alt') : gettext('checkout_confirm.img.mobilepay.alt')}/>
          </div>
          <div className="vipps-mobilepay-react-button-actions">
            <WPButton variant="primary" isLoading={isLoading} onClick={() => {
              setOption('vipps_checkout_enabled', boolToTruth(true));
              setStep('CHECKOUT');
            }}>
              {gettext('checkout_confirm.accept')}
            </WPButton>
            <WPButton variant="secondary">
              {gettext('checkout_confirm.skip')}
            </WPButton>
          </div>
        </div>
        </>
      )}

      {step === 'CHECKOUT' && (
        <>
        CHECKOUT
          <div className="vipps-mobilepay-react-button-actions">
            <WPButton variant="secondary" type="button" onClick={() => setStep('CHECKOUT')}>
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
