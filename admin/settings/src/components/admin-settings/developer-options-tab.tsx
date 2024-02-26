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
      <p className="vipps-mobilepay-react-tab-description">{gettext('developertitle.description')}</p>

      {/* Renders a checkbox to enable test mode */}
      <CheckboxFormField name="testmode" titleKey="testmode.title" descriptionKey="testmode.description" labelKey="testmode.label" />

      {/* Renders an input field for the TEST merchant serial number */}
      <InputFormField
        name="merchantSerialNumber_test"
        titleKey="merchantSerialNumber_test.title"
        labelKey="merchantSerialNumber_test.description"
      />

      {/* Renders an input field for the TEST client ID */}
      <InputFormField asterisk name="clientId_test" titleKey="clientId_test.title" labelKey="clientId_test.description" />

      {/* Renders an input field for the TEST client secret */}
      <InputFormField asterisk name="secret_test" titleKey="secret_test.title" labelKey="secret_test.description" />

      {/* Renders an input field for the TEST subscription key */}
      <InputFormField
        asterisk
        name="Ocp_Apim_Key_eCommerce_test"
        titleKey="Ocp_Apim_Key_eCommerce_test.title"
        labelKey="Ocp_Apim_Key_eCommerce_test.description"
      />
    </div>
  );
}
