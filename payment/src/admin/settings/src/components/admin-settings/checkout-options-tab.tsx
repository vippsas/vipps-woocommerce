import fixCheckoutName from '../../lib/fix-checkout-name';
import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { CheckboxFormField, InputFormField } from '../options-form-fields';
import { UnsafeHtmlText } from '../unsafe-html-text';

/**
 * A React component that renders the checkout options tab for the admin settings page.
 *
 * @returns The rendered checkout options tab.
 */
export function AdminSettingsCheckoutOptionsTab(): JSX.Element {
  const { getOption, hasOption } = useWP();

  const showPorterbuddy = getOption("vcs_porterbuddy") === "yes";
  const showHelthjem = getOption("vcs_helthjem") === "yes";
  const showExternalKlarna = hasOption("checkout_external_payments_klarna");
  const showExternals = showExternalKlarna;

  const paymentMethod = getOption('payment_method_name');
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">
        <UnsafeHtmlText
          htmlString={fixCheckoutName(gettext("checkout_options.description"), paymentMethod)}
        />
      </p>

      {/* Renders a checkbox to enable the Alternative screen  */}
      <CheckboxFormField
        name="vipps_checkout_enabled"
        titleKey="vipps_checkout_enabled.title"
        labelKey="vipps_checkout_enabled.label" 
        descriptionKey="vipps_checkout_enabled.description"
      />

      {/* Renders a checkbox to enable the creation of new customers on Checkout */}
      <CheckboxFormField
        name="checkoutcreateuser"
        titleKey="checkoutcreateuser.title"
        labelKey="checkoutcreateuser.label"
        descriptionKey="checkoutcreateuser.description"
      />

      {/* Renders a checkbox to enable dynamic shipping (inverted from static. LP 03.01.2025) */}
      <CheckboxFormField
        name="enablestaticshipping_checkout"
        titleKey="enablestaticshipping_checkout.title"
        labelKey="enablestaticshipping_checkout.label"
        descriptionKey="enablestaticshipping_checkout.description"
      />

      {/* Renders a checkbox to enable the sharing of user information */}
      <CheckboxFormField
        name="requireUserInfo_checkout"
        titleKey="requireUserInfo_checkout.title"
        labelKey="requireUserInfo_checkout.label"
        descriptionKey="requireUserInfo_checkout.description"
      />

      {/* Renders a checkbox to enable the dropping of address fields */}
      <CheckboxFormField
        name="noAddressFields"
        titleKey="noAddressFields.title"
        labelKey="noAddressFields.label"
        descriptionKey="noAddressFields.description"
      />

      {/* Renders a checkbox to enable the dropping of contact fields */}
      <CheckboxFormField
        name="noContactFields"
        titleKey="noContactFields.title"
        labelKey="noContactFields.label"
        descriptionKey="noContactFields.description"
      />

      {/* Checkout widget settings. LP 2025-05-12 */} 
      <h3 className="vipps-mobilepay-react-tab-description">
        {fixCheckoutName(gettext("checkout_widgets.title"), paymentMethod)}
      </h3>
      <p>{fixCheckoutName(gettext("checkout_widgets.description"), paymentMethod)}</p>

      {/* Coupon code widget checkbox. LP 2025-05-12 */} 
      <CheckboxFormField
        name="checkout_widget_coupon"
        titleKey="checkout_widget_coupon.title"
        descriptionKey="checkout_widget_coupon.description"
        labelKey="checkout_widget_coupon.label"
      />

      {/* Order notes widget checkbox. LP 2025-05-12 */} 
      <CheckboxFormField
        name="checkout_widget_ordernotes"
        titleKey="checkout_widget_ordernotes.title"
        descriptionKey="checkout_widget_ordernotes.description"
        labelKey="checkout_widget_ordernotes.label"
      />

      {showExternals && (
        <>
          <h3 className="vipps-mobilepay-react-trab-description">
            {fixCheckoutName(gettext("checkout_external_payment_title.title"), paymentMethod)}
          </h3>
          <p>{fixCheckoutName(gettext("checkout_external_payment_title.description"), paymentMethod)}</p>
          {showExternalKlarna && (
            <CheckboxFormField
              name="checkout_external_payments_klarna"
              titleKey="checkout_external_payments_klarna.title"
              labelKey="checkout_external_payments_klarna.label"
              descriptionKey="checkout_external_payments_klarna.description"
            />
          )}
        </>
      )}

      <h3 className="vipps-mobilepay-react-tab-description">
        {fixCheckoutName(gettext("checkout_shipping.title"), paymentMethod)}
      </h3>
      <p>{fixCheckoutName(gettext("checkout_shipping.description"), paymentMethod)}</p>

      {/* Renders a checkbox to enable Posten Norge  */}
      <CheckboxFormField
        name="vcs_posten"
        titleKey="vcs_posten.title"
        descriptionKey="vcs_posten.description"
        labelKey="vcs_posten.label"
      />

      {/* Renders a checkbox to enable Posten Nord */}
      <CheckboxFormField
        name="vcs_postnord"
        titleKey="vcs_postnord.title"
        descriptionKey="vcs_postnord.description"
        labelKey="vcs_postnord.label"
      />

      {/* Render a checkbox to enable Porterbuddy */}
      <CheckboxFormField
        name="vcs_porterbuddy"
        titleKey="vcs_porterbuddy.title"
        descriptionKey="vcs_porterbuddy.description"
        labelKey="vcs_porterbuddy.label"
      />

      {/* Display Porterbuddy input fields if Porterbuddy is enabled */}
      {showPorterbuddy && (
        <>
          {/* Renders a text input field for the Porterbuddy public token */}
          <InputFormField
            asterisk
            name="vcs_porterbuddy_publicToken"
            titleKey="vcs_porterbuddy_publicToken.title"
            descriptionKey="vcs_porterbuddy_publicToken.description"
          />

          {/* Renders a text input field for the Porterbuddy API key */}
          <InputFormField
            asterisk
            name="vcs_porterbuddy_apiKey"
            titleKey="vcs_porterbuddy_apiKey.title"
            descriptionKey="vcs_porterbuddy_apiKey.description"
          />

          {/* Renders a text input field for the Porterbuddy phone number */}
          <InputFormField
            name="vcs_porterbuddy_phoneNumber"
            titleKey="vcs_porterbuddy_phoneNumber.title"
            descriptionKey="vcs_porterbuddy_phoneNumber.description"
          />
        </>
      )}

      {/* Renders a checkbox to enable Helthjem */}
      <CheckboxFormField
        name="vcs_helthjem"
        titleKey="vcs_helthjem.title"
        descriptionKey="vcs_helthjem.description"
        labelKey="vcs_helthjem.label"
      />

      {/* Display Helthjem input fields if Helthjem is enabled */}
      {showHelthjem && (
        <>
          {/* Renders a text input field for the Helthjem Shop Id */}
          <InputFormField
            type="number"
            name="vcs_helthjem_shopId"
            titleKey="vcs_helthjem_shopId.title"
            descriptionKey="vcs_helthjem_shopId.description"
          />

          {/* Renders a text input field for the Helthjem Username */}
          <InputFormField
            name="vcs_helthjem_username"
            titleKey="vcs_helthjem_username.title"
            descriptionKey="vcs_helthjem_username.description"
          />

          {/* Renders a text input field for the Helthjem Password */}
          <InputFormField
            asterisk
            name="vcs_helthjem_password"
            titleKey="vcs_helthjem_password.title"
            descriptionKey="vcs_helthjem_password.description"
          />
        </>
      )}
    </div>
  );
}
