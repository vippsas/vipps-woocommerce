import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { CheckboxFormField, InputFormField } from '../options-form-fields';

/**
 * A React component that renders the checkout options tab for the admin settings page.
 *
 * @returns The rendered checkout options tab.
 */
export function AdminSettingsCheckoutOptionsTab(): JSX.Element {
  const { getOption } = useWP();

  const showPorterbuddy = getOption('vcs_porterbuddy') === 'yes';
  const showInstabox = getOption('vcs_instabox') === 'yes';
  const showHelthjem = getOption('vcs_helthjem') === 'yes';

  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('checkout_options_description')}</p>

      {/* Renders a checkbox to enable the Alternative screen  */}
      <CheckboxFormField
        name="vipps_checkout_enabled"
        titleKey="vipps_checkout_enabled_title"
        labelKey="vipps_checkout_enabled_label"
        descriptionKey="vipps_checkout_enabled_description"
      />

      {/* Renders a checkbox to enable the creation of new customers on Checkout */}
      <CheckboxFormField
        name="checkoutcreateuser"
        titleKey="checkoutcreateuser_title"
        labelKey="checkoutcreateuser_label"
        descriptionKey="checkoutcreateuser_description"
      />

      {/* Renders a checkbox to enable static shipping */}
      <CheckboxFormField
        name="enablestaticshipping_checkout"
        titleKey="enablestaticshipping_checkout_title"
        labelKey="enablestaticshipping_checkout_label"
        descriptionKey="enablestaticshipping_checkout_description"
      />

      {/* Renders a checkbox to enable the sharing of user information */}
      <CheckboxFormField
        name="requireUserInfo_checkout"
        titleKey="requireUserInfo_checkout_title"
        labelKey="requireUserInfo_checkout_label"
        descriptionKey="requireUserInfo_checkout_description"
      />

      {/* Renders a checkbox to enable the dropping of address fields */}
      <CheckboxFormField
        name="noAddressFields"
        titleKey="noAddressFields_title"
        labelKey="noAddressFields_label"
        descriptionKey="noAddressFields_description"
      />

      {/* Renders a checkbox to enable the dropping of contact fields */}
      <CheckboxFormField
        name="noContactFields"
        titleKey="noContactFields_title"
        labelKey="noContactFields_label"
        descriptionKey="noContactFields_description"
      />

      <h3 className="vipps-mobilepay-react-tab-description">{gettext('checkout_shipping_title')}</h3>
      <p>{gettext('checkout_shipping_description')}</p>

      {/* Renders a checkbox to enable Posten Norge  */}
      <CheckboxFormField
        name="vcs_posten"
        titleKey="vcs_posten_title"
        descriptionKey="vcs_posten_description"
        labelKey="vcs_posten_label"
      />

      {/* Renders a checkbox to enable Posten Nord */}
      <CheckboxFormField
        name="vcs_postnord"
        titleKey="vcs_postnord_title"
        descriptionKey="vcs_postnord_description"
        labelKey="vcs_postnord_label"
      />

      {/* Render a checkbox to enable Porterbuddy */}
      <CheckboxFormField
        name="vcs_porterbuddy"
        titleKey="vcs_porterbuddy_title"
        descriptionKey="vcs_porterbuddy_description"
        labelKey="vcs_porterbuddy_label"
      />

      {/* Display Porterbuddy input fields if Porterbuddy is enabled */}
      {showPorterbuddy && (
        <>
          {/* Renders a text input field for the Porterbuddy public token */}
          <InputFormField
            asterisk
            name="vcs_porterbuddy_publicToken"
            titleKey="vcs_porterbuddy_publicToken_title"
            descriptionKey="vcs_porterbuddy_publicToken_description"
          />

          {/* Renders a text input field for the Porterbuddy API key */}
          <InputFormField
            asterisk
            name="vcs_porterbuddy_apiKey"
            titleKey="vcs_porterbuddy_apiKey_title"
            descriptionKey="vcs_porterbuddy_apiKey_description"
          />

          {/* Renders a text input field for the Porterbuddy phone number */}
          <InputFormField
            name="vcs_porterbuddy_phoneNumber"
            titleKey="vcs_porterbuddy_phoneNumber_title"
            descriptionKey="vcs_porterbuddy_phoneNumber_description"
          />
        </>
      )}

      {/* Renders a checkbox to enable Instabox */}
      <CheckboxFormField
        name="vcs_instabox"
        titleKey="vcs_instabox_title"
        descriptionKey="vcs_instabox_description"
        labelKey="vcs_instabox_label"
      />

      {/* Display Instabox input fields if Instabox is enabled */}
      {showInstabox && (
        <>
          {/* Renders a text input field for the Instabox Client Id */}
          <InputFormField
            asterisk
            name="vcs_instabox_clientId"
            titleKey="vcs_instabox_clientId_title"
            descriptionKey="vcs_instabox_clientId_description"
          />

          {/* Renders a text input field for the Instabox Client Secret */}
          <InputFormField
            asterisk
            name="vcs_instabox_clientSecret"
            titleKey="vcs_instabox_clientSecret_title"
            descriptionKey="vcs_instabox_clientSecret_description"
          />
        </>
      )}

      {/* Renders a checkbox to enable Helthjem */}
      <CheckboxFormField
        name="vcs_helthjem"
        titleKey="vcs_helthjem_title"
        descriptionKey="vcs_helthjem_description"
        labelKey="vcs_helthjem_label"
      />

      {/* Display Helthjem input fields if Helthjem is enabled */}
      {showHelthjem && (
        <>
          {/* Renders a text input field for the Helthjem Shop Id */}
          <InputFormField
            type="number"
            name="vcs_helthjem_shopId"
            titleKey="vcs_helthjem_shopId_title"
            descriptionKey="vcs_helthjem_shopId_description"
          />

          {/* Renders a text input field for the Helthjem Username */}
          <InputFormField
            name="vcs_helthjem_username"
            titleKey="vcs_helthjem_username_title"
            descriptionKey="vcs_helthjem_username_description"
          />

          {/* Renders a text input field for the Helthjem Password */}
          <InputFormField
            asterisk
            name="vcs_helthjem_password"
            titleKey="vcs_helthjem_password_title"
            descriptionKey="vcs_helthjem_password_description"
          />
        </>
      )}
    </div>
  );
}
