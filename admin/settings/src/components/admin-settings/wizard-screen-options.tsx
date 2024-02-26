//import { useState } from 'react';
import { gettext } from '../../lib/wp-data';
//import { WPButton, WPForm } from '../form-elements';
import { CheckboxFormField, InputFormField, SelectFormField } from '../options-form-fields';
//import { useWP } from '../../wp-options-provider';

/**
 * A React component that renders the wizard screen options for the admin settings page.
 *
 * @returns The rendered wizard form fields.
 */
export function AdminSettingsWizardScreenOptions(): JSX.Element {
  return (
    <>
      {/* Renders a select field that specifies the payment method name (Vipps or MobilePay) */}
      <SelectFormField
        name="payment_method_name"
        titleKey="payment_method_name.title"
        descriptionKey="payment_method_name.label"
        options={[gettext('payment_method_name.options.Vipps'), gettext('payment_method_name.options.MobilePay')]}
      />

      {/* Renders a checkbox to enable the Alternative screen  */}
      <CheckboxFormField
        name="vipps_checkout_enabled"
        titleKey="vipps_checkout_enabled.title"
        labelKey="vipps_checkout_enabled.label"
        descriptionKey="vipps_checkout_enabled.description"
      />

      {/* Renders an input field for the merchant serial number */}
      <InputFormField asterisk name="merchantSerialNumber" titleKey="merchantSerialNumber.title" labelKey="merchantSerialNumber.label" />

      {/* Renders an input field for the VippsMobilePay client ID */}
      <InputFormField asterisk name="clientId" titleKey="clientId.title" labelKey="clientId.label" />

      {/* Renders an input field for the VippsMobilePay secret */}
      <InputFormField asterisk name="secret" titleKey="secret.title" labelKey="secret.label" />

      {/* Renders an input field for the VippsMobilePay Ocp_Apim_Key_eCommerce */}
      <InputFormField
        asterisk
        name="Ocp_Apim_Key_eCommerce"
        titleKey="Ocp_Apim_Key_eCommerce.title"
        labelKey="Ocp_Apim_Key_eCommerce.label"
      />
    </>
  );
}
