import { gettext } from '../../lib/wp-data';
import { CheckboxFormField, SelectFormField } from '../options-form-fields';

export function AdminSettingsExpressOptionsTab() {
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('express_options_description')}</p>

      <CheckboxFormField
        name="cartexpress"
        titleKey="cartexpress_title"
        labelKey="cartexpress_title"
        descriptionKey="cartexpress_description"
      />

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

      <CheckboxFormField
        name="singleproductexpressarchives"
        titleKey="singleproductexpressarchives_title"
        labelKey="singleproductexpressarchives_label"
        descriptionKey="singleproductexpressarchives_description"
      />

      <CheckboxFormField
        name="expresscheckout_termscheckbox"
        titleKey="expresscheckout_termscheckbox_title"
        labelKey="expresscheckout_termscheckbox_label"
        descriptionKey="expresscheckout_termscheckbox_description"
      />

      <CheckboxFormField
        name="expresscheckout_always_address"
        titleKey="expresscheckout_always_address_title"
        labelKey="expresscheckout_always_address_label"
        descriptionKey="expresscheckout_always_address_description"
      />

      <CheckboxFormField
        name="enablestaticshipping"
        titleKey="enablestaticshipping_title"
        labelKey="enablestaticshipping_label"
        descriptionKey="enablestaticshipping_description"
      />

      <CheckboxFormField
        name="expresscreateuser"
        titleKey="expresscreateuser_title"
        labelKey="expresscreateuser_label"
        descriptionKey="expresscreateuser_description"
      />

      <CheckboxFormField
        name="singleproductbuynowcompatmode"
        titleKey="singleproductbuynowcompatmode_title"
        labelKey="singleproductbuynowcompatmode_label"
        descriptionKey="singleproductbuynowcompatmode_description"
      />

      <CheckboxFormField
        name="deletefailedexpressorders"
        titleKey="deletefailedexpressorders_title"
        labelKey="deletefailedexpressorders_label"
        descriptionKey="deletefailedexpressorders_description"
      />
    </div>
  );
}
