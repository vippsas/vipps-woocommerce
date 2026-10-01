import { useState } from 'react';
import { detectPaymentMethodName } from '../../lib/payment-method';
import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { WPButton, WPFormField, WPLabel, truthToBool } from '../form-elements';
import { SwitchFormField, InputFormField, SelectFormField } from '../options-form-fields';
import { UnsafeHtmlText } from '../unsafe-html-text';

/**
 * A React component that renders the wizard screen options for the admin settings page.
 *
 * @returns The rendered wizard form fields.
 */
interface Props {
  isLoading: boolean;
}

// Step flow: Essential => EXPRESS => save and quit. 
type Steps = 'ESSENTIAL' | 'EXPRESS';

export function AdminSettingsWizardScreenOptions({ isLoading }: Props): JSX.Element {
  const { getOption, setOption } = useWP();
  
  const [step, setStep] = useState<Steps>('ESSENTIAL');
  const [prevStep, setPrevStep] = useState<Steps>('ESSENTIAL');

  const expressOverrides = [
    'cartexpress',
    'express_show_in_checkout',
    'singleproductexpressarchives',
    'express_singleproduct_enabled'
  ];

  var testSuffix = '';
  if (truthToBool(getOption('testmode'))) {
    testSuffix = '_test';
  }

  return (
    <>
      {step === 'ESSENTIAL' && (
        <>
          <h3 className="vipps-mobilepay-react-tab-description">{gettext('wizard_header.title')}</h3>
          <p>{gettext('wizard_header.description')}</p>
          <div className="vipps-mobilepay-form-container">
            <div className="vipps-mobilepay-form-col">
              {/* Toggle test mode */}
              <SwitchFormField 
                name="testmode"
                titleKey="testmode.title"
                descriptionKey="testmode_wizard.description"
              />

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

              {/* Inputs for keys. Uses test keys if testmode is switched on above. LP 2026-09-29 */}
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
                name={`merchantSerialNumber${testSuffix}`}
                titleKey={`merchantSerialNumber${testSuffix}.title`}
                descriptionKey={`merchantSerialNumber${testSuffix}.description`}
                required
              />

              {/* Renders an input field for the VippsMobilePay client ID */}
              <InputFormField
                asterisk
                name={`clientId${testSuffix}`}
                titleKey={`clientId${testSuffix}.title`}
                descriptionKey={`clientId${testSuffix}.description`}
                required
              />

              {/* Renders an input field for the VippsMobilePay secret */}
              <InputFormField
                asterisk
                name={`secret${testSuffix}`}
                titleKey={`secret${testSuffix}.title`}
                descriptionKey={`secret${testSuffix}.description`}
                required
              />

              {/* Renders an input field for the VippsMobilePay Ocp_Apim_Key_eCommerce */}
              <InputFormField
                asterisk
                name={"Ocp_Apim_Key_eCommerce" + testSuffix}
                titleKey={`Ocp_Apim_Key_eCommerce${testSuffix}.title`}
                descriptionKey={`Ocp_Apim_Key_eCommerce${testSuffix}.description`}
                required
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
                      if (!form) {
                          throw new Error("Form not found");
                      }

                      // Trigger validation and proceed to the next step if valid
                      if (form.reportValidity()) {
                        setPrevStep(step);
                        setStep('EXPRESS');
                      } else {
                           // no validity reported. 
                      }
                    }}
                  >
                    {/* no next step at this point if no checkout */}
                    {gettext('save_changes')}
                  </WPButton>
                </div>
              </WPFormField>
            </div>

            {/* Help box on the right hand side. LP 23.12.2024*/}
            <div className="vipps-mobilepay-form-col vipps-mobilepay-form-help-box">
              <div>
                <strong className="title">{gettext('help_box.get_started')}</strong><br/>
                <a href="https://wordpress.org/plugins/woo-vipps/" target="_blank">{gettext('help_box.documentation')}</a><br/>
                <a href="https://portal.vippsmobilepay.com" target="_blank">{gettext('help_box.portal')}</a>
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

      {step === 'EXPRESS' && (
        <>
          <div className="vipps-mobilepay-react-express-confirm">
            <h1 className="vipps-mobilepay-react-tab-description title">
              {gettext("express_options_wizard.title")}
            </h1>
            <p>{gettext("express_options_wizard.description")}</p>
            <p><a href="https://vippsmobilepay.com/nb-NO/express">{gettext("express_options_wizard.readmore")}</a></p>

            {/* Checkbox to enable express. LP 2026-08-13 */}
            <SwitchFormField
              name="express_enabled"
              titleKey={"express_enabled.title"}
              labelKey={"express_enabled.label"}
              descriptionKey={"express_enabled.description"}
              // Sync master toggle to overrides. LP 2026-09-29
              onChange={(value) => expressOverrides.forEach(option => setOption(option, value))}
            />

            <div className="vipps-mobilepay-react-button-actions">
              <WPButton variant="secondary" 
                type="button" 
                onClick={() => {
                  setStep(prevStep);
                  setPrevStep('ESSENTIAL');
                }}
              >{gettext('previous_step')}</WPButton>
              <WPButton variant="primary" isLoading={isLoading}>{gettext('save_changes')}</WPButton>
            </div>
          </div>
        </>
      )}
    </>
  );
}
