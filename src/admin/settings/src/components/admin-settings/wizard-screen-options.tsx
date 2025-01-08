import { useState } from 'react';
import fixCheckoutName from '../../lib/fix-checkout-name';
import { detectPaymentMethodName } from '../../lib/payment-method';
import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { WPButton, WPFormField, WPLabel, boolToTruth, truthToBool } from '../form-elements';
import { CheckboxFormField, InputFormField, SelectFormField } from '../options-form-fields';
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
  const { getOption, setOption, hasOption } = useWP();
  const [step, setStep] = useState<'ESSENTIAL' | 'CHECKOUT_CONFIRM' | 'CHECKOUT'>('ESSENTIAL');


  // For the "CHECKOUT" step
  const showPorterbuddy = getOption("vcs_porterbuddy") === "yes";
  const showHelthjem = getOption("vcs_helthjem") === "yes";
  const showExternalKlarna = hasOption("checkout_external_payments_klarna");
  const showExternals = showExternalKlarna;
  
  // TODO: test if this works when the first select onChange sets the option.
  const paymentMethod = getOption("payment_method_name");

  return (
    <>
      {step === 'ESSENTIAL' && (
        <>
          <h3 className="vipps-mobilepay-react-tab-description">{gettext('initial_settings')}</h3>
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
          <h1 className="title">{ fixCheckoutName(gettext( 'checkout_confirm.title' ), getOption("payment_method_name")) }</h1>
          <div className="body">
            <div className="list">
              <strong>{ fixCheckoutName(gettext( 'checkout_confirm.paragraph1.header' ), paymentMethod) }</strong>
              <ul>
                <li>{fixCheckoutName(gettext('checkout_confirm.paragraph1.first'), paymentMethod)}</li>
                <li>{fixCheckoutName(gettext('checkout_confirm.paragraph1.second'), paymentMethod)}</li>
                <li>{fixCheckoutName(gettext('checkout_confirm.paragraph1.third'), paymentMethod)}</li>
              </ul>
              <strong>{fixCheckoutName(gettext('checkout_confirm.paragraph2.header'), paymentMethod)}</strong>
              <ul>
                <li>{fixCheckoutName(gettext('checkout_confirm.paragraph2.first'), paymentMethod)}</li>
                <li>{fixCheckoutName(gettext('checkout_confirm.paragraph2.second'), paymentMethod)}</li>
              </ul>
            </div>
            <img src={gettext( paymentMethod === "Vipps" ? 'checkout_confirm.img.vipps.src' : 'checkout_confirm.img.mobilepay.src' )} alt={gettext( paymentMethod === "Vipps" ? 'checkout_confirm.img.vipps.alt' : 'checkout_confirm.img.vipps.alt') }/>
          </div>
          <div className="vipps-mobilepay-react-button-actions">
            <WPButton variant="primary" isLoading={isLoading} onClick={() => {
              setOption('vipps_checkout_enabled', boolToTruth(true));
              setStep('CHECKOUT');
            }}>
              {fixCheckoutName(gettext('checkout_confirm.accept'), paymentMethod)}
            </WPButton>
            <WPButton variant="secondary">
              {fixCheckoutName(gettext('checkout_confirm.skip'), paymentMethod)}
            </WPButton>
          </div>
        </div>
        </>
      )}

      {step === 'CHECKOUT' && (
        <>
          <div className="vipps-mobilepay-react-checkout-confirm">
            <h1 className="vipps-mobilepay-react-tab-description title">
              {fixCheckoutName(gettext("checkout_options_simple.title"), paymentMethod)}
            </h1>
            <p>{fixCheckoutName(gettext("checkout_options_simple.description"), paymentMethod)}</p>

            {/* Renders a checkbox to enable the creation of new customers on Checkout */}
            <CheckboxFormField
              name="checkoutcreateuser"
              titleKey={"checkoutcreateuser_simple.title"}
              labelKey={"checkoutcreateuser_simple.label"}
              descriptionKey={"checkoutcreateuser_simple.description"}
            />

            {/* Renders a checkbox to enable dynamic shipping (inverted checkbox from static shipping details) */}
            <CheckboxFormField
              name="enablestaticshipping_checkout"
              titleKey="enablestaticshipping_checkout_simple.title"
              labelKey="enablestaticshipping_checkout_simple.label"
              descriptionKey="enablestaticshipping_checkout_simple.description"
              inverted
            />

            {/* Renders a checkbox to enable address fields (inverted checkbox from dropping address fields) */}
            <CheckboxFormField
              name="noAddressFields"
              titleKey="noAddressFields_simple.title"
              labelKey="noAddressFields_simple.label"
              descriptionKey="noAddressFields_simple.description"
              inverted
            />

            <h3 className="vipps-mobilepay-react-tab-description">
              {fixCheckoutName(gettext("checkout_shipping_simple.title"), paymentMethod)}
            </h3>
            <p>{fixCheckoutName(gettext("checkout_shipping_simple.description"), paymentMethod)}</p>

            {/* Renders a checkbox to enable Posten Norge  */}
            <CheckboxFormField
              name="vcs_posten"
              titleKey="vcs_posten_simple.title"
              labelKey="vcs_posten_simple.label"
              descriptionKey="vcs_posten_simple.description"
            />

            {/* Renders a checkbox to enable Posten Nord */}
            <CheckboxFormField
              name="vcs_postnord"
              titleKey="vcs_postnord_simple.title"
              labelKey="vcs_postnord_simple.label"
              descriptionKey="vcs_postnord_simple.description"
            />

            {/* Render a checkbox to enable Porterbuddy */}
            <CheckboxFormField
              name="vcs_porterbuddy"
              titleKey="vcs_porterbuddy_simple.title"
              labelKey="vcs_porterbuddy_simple.label"
              descriptionKey="vcs_porterbuddy_simple.description"
            />

            {/* Display Porterbuddy input fields if Porterbuddy is enabled */}
            {showPorterbuddy && (
              <>
                {/* Renders a text input field for the Porterbuddy public token */}
                <InputFormField
                  asterisk
                  name="vcs_porterbuddy_publicToken"
                  titleKey="vcs_porterbuddy_publicToken_simple.title"
                  descriptionKey="vcs_porterbuddy_publicToken_simple.description"
                />

                {/* Renders a text input field for the Porterbuddy API key */}
                <InputFormField
                  asterisk
                  name="vcs_porterbuddy_apiKey"
                  titleKey="vcs_porterbuddy_apiKey_simple.title"
                  descriptionKey="vcs_porterbuddy_apiKey_simple.description"
                />

                {/* Renders a text input field for the Porterbuddy phone number */}
                <InputFormField
                  name="vcs_porterbuddy_phoneNumber"
                  titleKey="vcs_porterbuddy_phoneNumber_simple.title"
                  descriptionKey="vcs_porterbuddy_phoneNumber_simple.description"
                />
              </>
            )}

            {/* Renders a checkbox to enable Helthjem */}
            <CheckboxFormField
              name="vcs_helthjem"
              titleKey="vcs_helthjem_simple.title"
              labelKey="vcs_helthjem_simple.label"
              descriptionKey="vcs_helthjem_simple.description"
            />

            {/* Display Helthjem input fields if Helthjem is enabled */}
            {showHelthjem && (
              <>
                {/* Renders a text input field for the Helthjem Shop Id */}
                <InputFormField
                  type="number"
                  name="vcs_helthjem_shopId"
                  titleKey="vcs_helthjem_shopId_simple.title"
                  descriptionKey="vcs_helthjem_shopId_simple.description"
                />

                {/* Renders a text input field for the Helthjem Username */}
                <InputFormField
                  name="vcs_helthjem_username"
                  titleKey="vcs_helthjem_username_simple.title"
                  descriptionKey="vcs_helthjem_username_simple.description"
                />

                {/* Renders a text input field for the Helthjem Password */}
                <InputFormField
                  asterisk
                  name="vcs_helthjem_password"
                  titleKey="vcs_helthjem_password_simple.title"
                  descriptionKey="vcs_helthjem_password_simple.description"
                />
              </>
            )}

            {/* External payment methods */}
            {showExternals && (
              <>
                <h3 className="vipps-mobilepay-react-trab-description">
                  {fixCheckoutName(gettext("checkout_external_payment_title_simple.title"), paymentMethod)}
                </h3>
                <p>{fixCheckoutName(gettext("checkout_external_payment_title_simple.description"), paymentMethod)}</p>
                {showExternalKlarna && (
                  <CheckboxFormField
                    name="checkout_external_payments_klarna"
                    titleKey="checkout_external_payments_klarna_simple.title"
                    labelKey="checkout_external_payments_klarna_simple.label"
                    descriptionKey="checkout_external_payments_klarna_simple.description"
                  />
                )}
              </>
            )}
            <div className="vipps-mobilepay-react-button-actions">
              <WPButton variant="secondary" type="button" onClick={() => setStep('ESSENTIAL')}>
                {fixCheckoutName(gettext('previous_step'), paymentMethod)}
              </WPButton>
              <WPButton variant="primary" isLoading={isLoading}>
                {fixCheckoutName(gettext('save_changes'), paymentMethod)}
              </WPButton>
            </div>
          </div>
        </>
      )}
    </>
  );
}
