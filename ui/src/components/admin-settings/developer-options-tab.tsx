import { gettext } from '../../lib/wp-data';
import { CheckboxFormField, InputFormField } from '../options-form-fields';

export function AdminSettingsDeveloperOptionsTab(): JSX.Element {
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('developer_options_description')}</p>

      <CheckboxFormField name="testmode" titleKey="testmode_title" descriptionKey="testmode_description" labelKey="testmode_label" />

      <InputFormField
        name="merchantSerialNumber_test"
        titleKey="merchantSerialNumber_test_title"
        labelKey="merchantSerialNumber_test_description"
      />

      <InputFormField asterisk name="clientId_test" titleKey="clientId_test_title" labelKey="clientId_test_description" />

      <InputFormField asterisk name="secret_test" titleKey="secret_test_title" labelKey="secret_test_description" />

      <InputFormField
        asterisk
        name="Ocp_Apim_Key_eCommerce_test"
        titleKey="Ocp_Apim_Key_eCommerce_test_title"
        labelKey="Ocp_Apim_Key_eCommerce_test_description"
      />
    </div>
  );
}
