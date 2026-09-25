import { gettext } from "../../lib/wp-data";
import { Collapsible } from "../collapsible";
import {
  InputFormField,
} from "../options-form-fields";

/**
 * A React component that renders the main options tab for the admin settings page.
 *
 * @returns The rendered main options tab.
 */
export function AdminSettingsKeysOptionsTab(): JSX.Element {
  return (
    <div>
      {/* Prod keys section */}
      <Collapsible title={gettext("production_keys_section.title")}>
        <p className="vipps-mobilepay-react-tab-description"></p>

        {/* Renders an input field for the merchant serial number */}
        <InputFormField
          asterisk
          name="merchantSerialNumber"
          titleKey="merchantSerialNumber.title"
          descriptionKey="merchantSerialNumber.description"
        />

        {/* Renders an input field for the VippsMobilePay client ID */}
        <InputFormField
          asterisk
          name="clientId"
          titleKey="clientId.title"
          descriptionKey="clientId.description"
        />

        {/* Renders an input field for the VippsMobilePay secret */}
        <InputFormField
          asterisk
          name="secret"
          titleKey="secret.title"
          descriptionKey="secret.description"
        />

        {/* Renders an input field for the VippsMobilePay Ocp_Apim_Key_eCommerce */}
        <InputFormField
          asterisk
          name="Ocp_Apim_Key_eCommerce"
          titleKey="Ocp_Apim_Key_eCommerce.title"
          descriptionKey="Ocp_Apim_Key_eCommerce.description"
        />
      </Collapsible>

      {/* Test keys section */}
      <Collapsible title={gettext("test_keys_section.title")}>
        <p className="vipps-mobilepay-react-tab-description"></p>

        {/* Renders an input field for the TEST merchant serial number */}
        <InputFormField
          asterisk
          name="merchantSerialNumber_test"
          titleKey="merchantSerialNumber_test.title"
          descriptionKey="merchantSerialNumber_test.description"
        />

        {/* Renders an input field for the TEST VippsMobilePay client ID */}
        <InputFormField
          asterisk
          name="clientId_test"
          titleKey="clientId_test.title"
          descriptionKey="clientId_test.description"
        />

        {/* Renders an input field for the TEST VippsMobilePay secret */}
        <InputFormField
          asterisk
          name="secret_test"
          titleKey="secret_test.title"
          descriptionKey="secret_test.description"
        />

        {/* Renders an input field for the TEST VippsMobilePay Ocp_Apim_Key_eCommerce */}
        <InputFormField
          asterisk
          name="Ocp_Apim_Key_eCommerce_test"
          titleKey="Ocp_Apim_Key_eCommerce_test.title"
          descriptionKey="Ocp_Apim_Key_eCommerce_test.description"
        />
      </Collapsible>
    </div>
  );
}
