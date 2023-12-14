import { gettext } from '../../lib/wp-data';
import { CheckboxFormField, InputFormField, SelectFormField, TextareaFormField } from '../options-form-fields';

export function AdminSettingsMainOptionsTab() {
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description"></p>

      <CheckboxFormField name="enabled" titleKey="enabled_title" labelKey="enabled_label" />

      <SelectFormField
        name="payment_method_name"
        titleKey="payment_method_name_title"
        descriptionKey="payment_method_name_label"
        options={[gettext('payment_method_name_options_vipps'), gettext('payment_method_name_options_mobilepay')]}
      />

      <InputFormField name="orderprefix" titleKey="orderprefix_title" labelKey="orderprefix_label" pattern="[a-zA-Z0-9-]+" required />
      <InputFormField asterisk name="merchantSerialNumber" titleKey="merchantSerialNumber_title" labelKey="merchantSerialNumber_label" />
      <InputFormField asterisk name="clientId" titleKey="clientId_title" labelKey="clientId_label" />
      <InputFormField asterisk name="secret" titleKey="secret_title" labelKey="secret_label" />

      <InputFormField name="Ocp_Apim_Key_eCommerce" titleKey="Ocp_Apim_Key_eCommerce_title" labelKey="Ocp_Apim_Key_eCommerce_label" />

      <SelectFormField
        name="result_status"
        titleKey="result_status_title"
        descriptionKey="result_status_description"
        options={[gettext('result_status_options_on-hold'), gettext('result_status_options_processing')]}
      />

      <InputFormField name="title" titleKey="title_title" labelKey="title_description" />

      <TextareaFormField name="description" titleKey="description_title" labelKey="description_description" rows={5} />

      <CheckboxFormField name="vippsdefault" titleKey="vippsdefault_title" labelKey="vippsdefault_label" />
    </div>
  );
}
