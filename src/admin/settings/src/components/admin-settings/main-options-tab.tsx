import { detectPaymentMethodName, getPaymentMethodSupportedCurrencies, isPaymentMethodCurrencySupported } from '../../lib/payment-method';
import { useWP } from '../../wp-options-provider';
import { getMetadata, gettext } from '../../lib/wp-data';
import { CheckboxFormField, InputFormField, SelectFormField, TextareaFormField } from '../options-form-fields';

/**
 * A React component that renders the main options tab for the admin settings page.
 *
 * @returns The rendered main options tab.
 */
export function AdminSettingsMainOptionsTab(): JSX.Element {
  const { setOption, getOption } = useWP();
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description"></p>

      {/* Renders a checkbox that specifies whether or not the plugin is enabled  */}
      <CheckboxFormField name="enabled" titleKey="enabled.title" labelKey="enabled.label" />

      {/* Renders a select field that specifies the country */}
      <SelectFormField
        name="country"
        titleKey="country.title"
        descriptionKey="country.description"
        includeEmptyOption={false}
        required
        onChange={(e) => {
          // Set the payment method name based on the selected country
          const paymentMethod = detectPaymentMethodName(e.target.value);
          // Only update the payment method name if it is not already set
          if (!getOption('payment_method_name')) {
            setOption('payment_method_name', paymentMethod);
          }
        }}
        options={[
          { label: gettext('country.options.NO'), value: 'NO' },
          { label: gettext('country.options.SE'), value: 'SE' },
          { label: gettext('country.options.FI'), value: 'FI' },
          { label: gettext('country.options.DK'), value: 'DK' }
        ]}
      />

      {/* Renders a select field that specifies the payment method name (Vipps or MobilePay) */}
      <SelectFormField
        name="payment_method_name"
        titleKey="payment_method_name.title"
        descriptionKey="payment_method_name.description"
        includeEmptyOption={false}
        required
        error={
          !isPaymentMethodCurrencySupported(getOption('payment_method_name'), getMetadata('currency'))
            ? `${getOption('payment_method_name')} does not support your store currency (${getMetadata('currency')}). Supported currencies: ${getPaymentMethodSupportedCurrencies(getOption('payment_method_name')).join(', ')}`
            : undefined
        }
        options={[
          { label: gettext('payment_method_name.options.Vipps'), value: 'Vipps' },
          { label: gettext('payment_method_name.options.MobilePay'), value: 'MobilePay' }
        ]}
      />

      {/* Renders an input field for the order prefix */}
      <InputFormField
        name="orderprefix"
        titleKey="orderprefix.title"
        descriptionKey="orderprefix.description"
        pattern="[a-zA-Z0-9-]+"
        required
      />

      {/* Renders an input field for the merchant serial number */}
      <InputFormField
        asterisk
        name="merchantSerialNumber"
        titleKey="merchantSerialNumber.title"
        descriptionKey="merchantSerialNumber.description"
      />

      {/* Renders an input field for the VippsMobilePay client ID */}
      <InputFormField asterisk name="clientId" titleKey="clientId.title" descriptionKey="clientId.description" />

      {/* Renders an input field for the VippsMobilePay secret */}
      <InputFormField asterisk name="secret" titleKey="secret.title" descriptionKey="secret.description" />

      {/* Renders an input field for the VippsMobilePay Ocp_Apim_Key_eCommerce */}
      <InputFormField
        asterisk
        name="Ocp_Apim_Key_eCommerce"
        titleKey="Ocp_Apim_Key_eCommerce.title"
        descriptionKey="Ocp_Apim_Key_eCommerce.description"
      />

      {/* Renders a select field for the result status (On-Hold or Processing) */}
      <SelectFormField
        name="result_status"
        titleKey="result_status.title"
        descriptionKey="result_status.description"
        options={[
          { label: gettext('result_status.options.on-hold'), value: 'on-hold' },
          { label: gettext('result_status.options.processing'), value: 'processing' }
        ]}
      />

      {/* Renders a textarea field for the description */}
      <TextareaFormField name="description" titleKey="description.title" descriptionKey="description.description" rows={5} />

      {/* Renders a checkbox that specifies whether or not Vipps is the default payment method */}
      <CheckboxFormField name="vippsdefault" titleKey="vippsdefault.title" labelKey="vippsdefault.label" />
    </div>
  );
}
