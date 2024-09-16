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
      <p className="vipps-mobilepay-react-tab-description">{gettext('express_options.description')}</p>

      {/* Renders a checkbox to enable Express Checkout in cart */}
      <CheckboxFormField
        name="cartexpress"
        titleKey="cartexpress.title"
        labelKey="cartexpress.title"
        descriptionKey="cartexpress.description"
      />

      {/* Renders a select field that allows an admin to specify which products should have the "express checkout" option enabled  */}
      <SelectFormField
        name="singleproductexpress"
        titleKey="singleproductexpress.title"
        descriptionKey="singleproductexpress.description"
        options={[
          { value: 'none', label: gettext('singleproductexpress.options.none') },
          { value: 'some', label: gettext('singleproductexpress.options.some') },
          { value: 'all', label: gettext('singleproductexpress.options.all') }
        ]}
      />

      {/* Renders a checkbox to enable the 'Buy now' button on catalog pages */}
      <CheckboxFormField
        name="singleproductexpressarchives"
        titleKey="singleproductexpressarchives.title"
        labelKey="singleproductexpressarchives.label"
        descriptionKey="singleproductexpressarchives.description"
      />

      {/* Renders a checkbox to enable whether or not users should be asked if they've read the store's terms and conditions */}
      <CheckboxFormField
        name="expresscheckout_termscheckbox"
        titleKey="expresscheckout_termscheckbox.title"
        labelKey="expresscheckout_termscheckbox.label"
        descriptionKey="expresscheckout_termscheckbox.description"
      />

      {/* Renders a checkbox to enable whether or not the users should always be asked for an address */}
      <CheckboxFormField
        name="expresscheckout_always_address"
        titleKey="expresscheckout_always_address.title"
        labelKey="expresscheckout_always_address.label"
        descriptionKey="expresscheckout_always_address.description"
      />

      {/* Renders a checkbox to enable static shipping for Express Checkout */}
      <CheckboxFormField
        name="enablestaticshipping"
        titleKey="enablestaticshipping.title"
        labelKey="enablestaticshipping.label"
        descriptionKey="enablestaticshipping.description"
      />

      {/* Renders a checkbox to enable whether or not new users should be created when using Express Checkout */}
      <CheckboxFormField
        name="expresscreateuser"
        titleKey="expresscreateuser.title"
        labelKey="expresscreateuser.label"
        descriptionKey="expresscreateuser.description"
      />

      {/* Renders a checkbox to enable compatibility mode for the "Buy now" button */}
      <CheckboxFormField
        name="singleproductbuynowcompatmode"
        titleKey="singleproductbuynowcompatmode.title"
        labelKey="singleproductbuynowcompatmode.label"
        descriptionKey="singleproductbuynowcompatmode.description"
      />

      {/* Renders a checkbox to enable whether or not failed Express Checkout orders should be deleted */}
      <CheckboxFormField
        name="deletefailedexpressorders"
        titleKey="deletefailedexpressorders.title"
        labelKey="deletefailedexpressorders.label"
        descriptionKey="deletefailedexpressorders.description"
      />
    </div>
  );
}
