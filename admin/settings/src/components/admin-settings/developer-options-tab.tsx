import { gettext } from '../../lib/wp-data';
import { CheckboxFormField, InputFormField } from '../options-form-fields';

/**
 * A React component that renders the developer options tab for the admin settings page.
 *
 * @returns The rendered developer options tab.
 */
export function AdminSettingsDeveloperOptionsTab(): JSX.Element {
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('developer_options_description')}</p>

      {/* Renders a checkbox to enable test mode */}
      <CheckboxFormField name="testmode" titleKey="testmode_title" descriptionKey="testmode_description" labelKey="testmode_label" />

      {/* Renders an input field for the TEST merchant serial number */}
      <InputFormField
        name="merchantSerialNumber_test"
        titleKey="merchantSerialNumber_test_title"
        labelKey="merchantSerialNumber_test_description"
      />

      {/* Renders an input field for the TEST client ID */}
      <InputFormField asterisk name="clientId_test" titleKey="clientId_test_title" labelKey="clientId_test_description" />

      {/* Renders an input field for the TEST client secret */}
      <InputFormField asterisk name="secret_test" titleKey="secret_test_title" labelKey="secret_test_description" />

      {/* Renders an input field for the TEST subscription key */}
      <InputFormField
        asterisk
        name="Ocp_Apim_Key_eCommerce_test"
        titleKey="Ocp_Apim_Key_eCommerce_test_title"
        labelKey="Ocp_Apim_Key_eCommerce_test_description"
      />
    </div>
  );
}
