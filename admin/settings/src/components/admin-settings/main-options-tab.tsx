import { gettext } from '../../lib/wp-data';
import { CheckboxFormField, InputFormField, SelectFormField, TextareaFormField } from '../options-form-fields';

/**
 * A React component that renders the main options tab for the admin settings page.
 *
 * @returns The rendered main options tab.
 */
export function AdminSettingsMainOptionsTab(): JSX.Element {
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description"></p>

      {/* Renders a checkbox that specifies whether or not the plugin is enabled  */}
      <CheckboxFormField name="enabled" titleKey="enabled_title" labelKey="enabled_label" />

      {/* Renders a select field that specifies the payment method name (Vipps or MobilePay) */}
      <SelectFormField
        name="payment_method_name"
        titleKey="payment_method_name_title"
        descriptionKey="payment_method_name_label"
        options={[gettext('payment_method_name_options_vipps'), gettext('payment_method_name_options_mobilepay')]}
      />

      {/* Renders an input field for the order prefix */}
      <InputFormField name="orderprefix" titleKey="orderprefix_title" labelKey="orderprefix_label" pattern="[a-zA-Z0-9-]+" required />

      {/* Renders an input field for the merchant serial number */}
      <InputFormField asterisk name="merchantSerialNumber" titleKey="merchantSerialNumber_title" labelKey="merchantSerialNumber_label" />

      {/* Renders an input field for the VippsMobilePay client ID */}
      <InputFormField asterisk name="clientId" titleKey="clientId_title" labelKey="clientId_label" />

      {/* Renders an input field for the VippsMobilePay secret */}
      <InputFormField asterisk name="secret" titleKey="secret_title" labelKey="secret_label" />

      {/* Renders an input field for the VippsMobilePay Ocp_Apim_Key_eCommerce */}
      <InputFormField asterisk name="Ocp_Apim_Key_eCommerce" titleKey="Ocp_Apim_Key_eCommerce_title" labelKey="Ocp_Apim_Key_eCommerce_label" />

      {/* Renders a select field for the result status (On-Hold or Processing) */}
      <SelectFormField
        name="result_status"
        titleKey="result_status_title"
        descriptionKey="result_status_description"
        options={[gettext('result_status_options_on-hold'), gettext('result_status_options_processing')]}
      />

      {/* Renders an input field for the title */}
      <InputFormField name="title" titleKey="title_title" labelKey="title_description" />

      {/* Renders a textarea field for the description */}
      <TextareaFormField name="description" titleKey="description_title" labelKey="description_description" rows={5} />

      {/* Renders a checkbox that specifies whether or not Vipps is the default payment method */}
      <CheckboxFormField name="vippsdefault" titleKey="vippsdefault_title" labelKey="vippsdefault_label" />
    </div>
  );
}
