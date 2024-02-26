import { gettext } from '../../lib/wp-data';
import { CheckboxFormField, SelectFormField } from '../options-form-fields';

/**
 * A React component that renders the express options tab for the admin settings page.
 *
 * @returns The rendered express options tab.
 */
export function AdminSettingsExpressOptionsTab(): JSX.Element {
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('express_options_description')}</p>

      {/* Renders a checkbox to enable Express Checkout in cart */}
      <CheckboxFormField
        name="cartexpress"
        titleKey="cartexpress_title"
        labelKey="cartexpress_title"
        descriptionKey="cartexpress_description"
      />

      {/* Renders a select field that allows an admin to specify which products should have the "express checkout" option enabled  */}
      <SelectFormField
        name="singleproductexpress"
        titleKey="singleproductexpress_title"
        descriptionKey="singleproductexpress_description"
        options={[
          gettext('singleproductexpress_options_none'),
          gettext('singleproductexpress_options_some'),
          gettext('singleproductexpress_options_all')
        ]}
      />

      {/* Renders a checkbox to enable the 'Buy now' button on catalog pages */}
      <CheckboxFormField
        name="singleproductexpressarchives"
        titleKey="singleproductexpressarchives_title"
        labelKey="singleproductexpressarchives_label"
        descriptionKey="singleproductexpressarchives_description"
      />

      {/* Renders a checkbox to enable whether or not users should be asked if they've read the store's terms and conditions */}
      <CheckboxFormField
        name="expresscheckout_termscheckbox"
        titleKey="expresscheckout_termscheckbox_title"
        labelKey="expresscheckout_termscheckbox_label"
        descriptionKey="expresscheckout_termscheckbox_description"
      />

      {/* Renders a checkbox to enable whether or not the users should always be asked for an address */}
      <CheckboxFormField
        name="expresscheckout_always_address"
        titleKey="expresscheckout_always_address_title"
        labelKey="expresscheckout_always_address_label"
        descriptionKey="expresscheckout_always_address_description"
      />

      {/* Renders a checkbox to enable static shipping for Express Checkout */}
      <CheckboxFormField
        name="enablestaticshipping"
        titleKey="enablestaticshipping_title"
        labelKey="enablestaticshipping_label"
        descriptionKey="enablestaticshipping_description"
      />

      {/* Renders a checkbox to enable whether or not new users should be created when using Express Checkout */}
      <CheckboxFormField
        name="expresscreateuser"
        titleKey="expresscreateuser_title"
        labelKey="expresscreateuser_label"
        descriptionKey="expresscreateuser_description"
      />

      {/* Renders a checkbox to enable compatibility mode for the "Buy now" button */}
      <CheckboxFormField
        name="singleproductbuynowcompatmode"
        titleKey="singleproductbuynowcompatmode_title"
        labelKey="singleproductbuynowcompatmode_label"
        descriptionKey="singleproductbuynowcompatmode_description"
      />

      {/* Renders a checkbox to enable whether or not failed Express Checkout orders should be deleted */}
      <CheckboxFormField
        name="deletefailedexpressorders"
        titleKey="deletefailedexpressorders_title"
        labelKey="deletefailedexpressorders_label"
        descriptionKey="deletefailedexpressorders_description"
      />
    </div>
  );
}
