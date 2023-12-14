import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { CheckboxFormField, InputFormField } from '../options-form-fields';

export function AdminSettingsCheckoutOptionsTab() {
  const { getOption } = useWP();

  const showPorterbuddy = getOption('vcs_porterbuddy') === 'yes';
  const showInstabox = getOption('vcs_instabox') === 'yes';
  const showHelthjem = getOption('vcs_helthjem') === 'yes';

  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('checkout_options_description')}</p>

      <CheckboxFormField
        name="vipps_checkout_enabled"
        titleKey="vipps_checkout_enabled_title"
        labelKey="vipps_checkout_enabled_label"
        descriptionKey="vipps_checkout_enabled_description"
      />

      <CheckboxFormField
        name="checkoutcreateuser"
        titleKey="checkoutcreateuser_title"
        labelKey="checkoutcreateuser_label"
        descriptionKey="checkoutcreateuser_description"
      />

      <CheckboxFormField
        name="enablestaticshipping_checkout"
        titleKey="enablestaticshipping_checkout_title"
        labelKey="enablestaticshipping_checkout_label"
        descriptionKey="enablestaticshipping_checkout_description"
      />

      <CheckboxFormField
        name="requireUserInfo_checkout"
        titleKey="requireUserInfo_checkout_title"
        labelKey="requireUserInfo_checkout_label"
        descriptionKey="requireUserInfo_checkout_description"
      />

      <CheckboxFormField
        name="noAddressFields"
        titleKey="noAddressFields_title"
        labelKey="noAddressFields_label"
        descriptionKey="noAddressFields_description"
      />

      <CheckboxFormField
        name="noContactFields"
        titleKey="noContactFields_title"
        labelKey="noContactFields_label"
        descriptionKey="noContactFields_description"
      />

      <h3 className="vipps-mobilepay-react-tab-description">{gettext('checkout_shipping_title')}</h3>
      <p>{gettext('checkout_shipping_description')}</p>

      <CheckboxFormField
        name="vcs_posten"
        titleKey="vcs_posten_title"
        descriptionKey="vcs_posten_description"
        labelKey="vcs_posten_label"
      />

      <CheckboxFormField
        name="vcs_postnord"
        titleKey="vcs_postnord_title"
        descriptionKey="vcs_postnord_description"
        labelKey="vcs_postnord_label"
      />

      <CheckboxFormField
        name="vcs_porterbuddy"
        titleKey="vcs_porterbuddy_title"
        descriptionKey="vcs_porterbuddy_description"
        labelKey="vcs_porterbuddy_label"
      />

      {showPorterbuddy && (
        <>
          <InputFormField
            asterisk
            name="vcs_porterbuddy_publicToken"
            titleKey="vcs_porterbuddy_publicToken_title"
            descriptionKey="vcs_porterbuddy_publicToken_description"
          />
          <InputFormField
            asterisk
            name="vcs_porterbuddy_apiKey"
            titleKey="vcs_porterbuddy_apiKey_title"
            descriptionKey="vcs_porterbuddy_apiKey_description"
          />
          <InputFormField
            name="vcs_porterbuddy_phoneNumber"
            titleKey="vcs_porterbuddy_phoneNumber_title"
            descriptionKey="vcs_porterbuddy_phoneNumber_description"
          />
        </>
      )}

      <CheckboxFormField
        name="vcs_instabox"
        titleKey="vcs_instabox_title"
        descriptionKey="vcs_instabox_description"
        labelKey="vcs_instabox_label"
      />

      {showInstabox && (
        <>
          <InputFormField
            asterisk
            name="vcs_instabox_clientId"
            titleKey="vcs_instabox_clientId_title"
            descriptionKey="vcs_instabox_clientId_description"
          />
          <InputFormField
            asterisk
            name="vcs_instabox_clientSecret"
            titleKey="vcs_instabox_clientSecret_title"
            descriptionKey="vcs_instabox_clientSecret_description"
          />
        </>
      )}

      <CheckboxFormField
        name="vcs_helthjem"
        titleKey="vcs_helthjem_title"
        descriptionKey="vcs_helthjem_description"
        labelKey="vcs_helthjem_label"
      />

      {showHelthjem && (
        <>
          <InputFormField
            type="number"
            name="vcs_helthjem_shopId"
            titleKey="vcs_helthjem_shopId_title"
            descriptionKey="vcs_helthjem_shopId_description"
          />
          <InputFormField
            name="vcs_helthjem_username"
            titleKey="vcs_helthjem_username_title"
            descriptionKey="vcs_helthjem_username_description"
          />
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
