import { useState } from 'react';
import { gettext } from '../lib/wp-data';
import { useWP } from '../wp-options-provider';
import { UnsafeHtmlText } from './unsafe-html-text';
import { WPCheckbox, WPFormField, WPInput, WPLabel, WPOption, WPSelect, WPTextarea } from './form-elements';

interface Props {
  name: string;
  titleKey: string;
  labelKey: string;
  descriptionKey?: string;
}

/**
 * Checkbox form field component.
 * Reads and updates the WP data available in the WPOptionsProvider.
 */
export function CheckboxFormField({ name, titleKey, labelKey, descriptionKey }: Props) {
  const { getOption, setOption } = useWP();

  return (
    <WPFormField>
      <WPLabel htmlFor={`woocommerce_vipps_${name}`}>{gettext(titleKey)}</WPLabel>
      <div className="vipps-mobilepay-react-col">
        <div className="vipps-mobilepay-react-row-center">
          <WPCheckbox
            id={`woocommerce_vipps_${name}`}
            name={`woocommerce_vipps_${name}`}
            checked={getOption(name)}
            onChange={(value) => setOption(name, value)}
          />
          {labelKey && (
            <label htmlFor={`woocommerce_vipps_${name}`}>
              <UnsafeHtmlText htmlString={gettext(labelKey)} />
            </label>
          )}
        </div>
        {descriptionKey && <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={gettext(descriptionKey)} />}
      </div>
    </WPFormField>
  );
}

interface SelectFormFieldProps {
  name: string;
  titleKey: string;
  descriptionKey: string;
  options: string[];
}

/**
 * Select form field component.
 * Reads and updates the WP data available in the WPOptionsProvider.
 */
export function SelectFormField({ name, titleKey, descriptionKey, options }: SelectFormFieldProps) {
  const { getOption, setOption } = useWP();

  return (
    <WPFormField>
      <WPLabel htmlFor={`woocommerce_vipps_${name}`}>{gettext(titleKey)}</WPLabel>
      <div className="vipps-mobilepay-react-col">
        <WPSelect
          id={`woocommerce_vipps_${name}`}
          name={`woocommerce_vipps_${name}`}
          onChange={(e) => setOption(name, e.target.value)}
          value={getOption(name)}
          required
        >
          {options.map((option) => (
            <WPOption key={option} value={option}>
              {option}
            </WPOption>
          ))}
        </WPSelect>
        {descriptionKey && <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={gettext(descriptionKey)} />}
      </div>
    </WPFormField>
  );
}

interface InputFormFieldProps {
  name: string;
  titleKey: string;
  labelKey?: string;
  descriptionKey?: string;
  pattern?: string;
  required?: boolean;
  asterisk?: boolean;
  type?: string;
}

/**
 * Input form field component.
 * Reads and updates the WP data available in the WPOptionsProvider.
 */
export function InputFormField({
  name,
  titleKey,
  labelKey,
  descriptionKey,
  pattern,
  required,
  asterisk,
  type = 'text'
}: InputFormFieldProps) {
  const { getOption, setOption } = useWP();
  const [isFocused, setIsFocused] = useState(false);
  const shouldHideValue = !isFocused && asterisk;

  return (
    <WPFormField>
      <WPLabel htmlFor={`woocommerce_vipps_${name}`}>{gettext(titleKey)}</WPLabel>
      <div className="vipps-mobilepay-react-col">
        <WPInput
          id={`woocommerce_vipps_${name}`}
          name={`woocommerce_vipps_${name}`}
          onChange={(e) => setOption(name, e.target.value)}
          value={getOption(name)}
          pattern={pattern}
          required={required}
          onFocus={() => setIsFocused(true)}
          onBlur={() => setIsFocused(false)}
          type={shouldHideValue ? 'password' : type}
        />
        <div>{labelKey && <UnsafeHtmlText htmlString={gettext(labelKey)} />}</div>
        {descriptionKey && <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={gettext(descriptionKey)} />}
      </div>
    </WPFormField>
  );
}

interface TextareaFormFieldProps {
  name: string;
  titleKey: string;
  labelKey?: string;
  descriptionKey?: string;
  rows?: number;
}

/**
 * Textarea form field component.
 * Reads and updates the WP data available in the WPOptionsProvider.
 */
export function TextareaFormField({ name, titleKey, labelKey, descriptionKey, rows = 5 }: TextareaFormFieldProps) {
  const { getOption, setOption } = useWP();
  return (
    <WPFormField>
      <WPLabel htmlFor={`woocommerce_vipps_${name}`}>{gettext(titleKey)}</WPLabel>
      <div className="vipps-mobilepay-react-col">
        <WPTextarea
          id={`woocommerce_vipps_${name}`}
          name={`woocommerce_vipps_${name}`}
          onChange={(e) => setOption(name, e.target.value)}
          value={getOption(name)}
          rows={rows}
        />
        <div>{labelKey && <UnsafeHtmlText htmlString={gettext(labelKey)} />}</div>
        {descriptionKey && <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={gettext(descriptionKey)} />}
      </div>
    </WPFormField>
  );
}
