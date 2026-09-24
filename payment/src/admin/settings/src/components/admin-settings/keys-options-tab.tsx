import {
  InputFormField,
} from "../options-form-fields";

/**
 * A React component that renders the main options tab for the admin settings page.
 *
 * @returns The rendered main options tab.
 */
export function AdminSettingsKeysOptionsTab(): JSX.Element {
  // LP TODO: move test keys into here too after creating a dropdown reusable component for the redesign. Then, developer mode tab can actually be deleted, and testmode checkbox can be moved into advanced. LP 2026-09-24
  return (
    <div>
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
    </div>
  );
}
