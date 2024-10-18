import { useState } from 'react';
import { gettext } from '../lib/wp-data';
import { useWP } from '../wp-options-provider';
import { UnsafeHtmlText } from './unsafe-html-text';
import { WPCheckbox, WPFormField, WPInput, WPLabel, WPOption, WPSelect, WPTextarea } from './form-elements';

/**
 * Represents the props for the options form fields component.
 */
interface Props {
  /**
   * The name of the field.
   */
  name: string;

  /**
   * The key for the title of the field.
   */
  titleKey: string;

  /**
   * The key for the label of the field.
   */
  labelKey: string;

  /**
   * The optional key for the description of the field.
   */
  descriptionKey?: string;
}

/**
 * React component which renders a Checkbox form field component.
 *
 * Reads and updates the WP data available in the WPOptionsProvider.
 */
export function CheckboxFormField({ name, titleKey, labelKey, descriptionKey }: Props) {
  const { getOption, setOption } = useWP();

  return (
    <WPFormField>
      <WPLabel htmlFor={name}>{gettext(titleKey)}</WPLabel>
      <div className="vipps-mobilepay-react-col">
        <div className="vipps-mobilepay-react-row-center">
          <WPCheckbox id={name} name={name} checked={getOption(name)} onChange={(value) => setOption(name, value)} />
          {labelKey && (
            <label htmlFor={name}>
              <UnsafeHtmlText htmlString={gettext(labelKey)} />
            </label>
          )}
        </div>
        {descriptionKey && <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={gettext(descriptionKey)} />}
      </div>
    </WPFormField>
  );
}

/**
 * Represents the props for a select form field.
 */
interface SelectFormFieldProps {
  /**
   * The name of the form field.
   */
  name: string;

  /**
   * The key for the title of the form field.
   */
  titleKey: string;

  /**
   * The key for the description of the form field.
   */
  descriptionKey: string;

  /**
   * The key for the label of the form field.
   */
  labelKey?: string;

  /**
   * The options for the select form field.
   */
  options: { label: string; value: string }[];

  /**
   * The function to call when the select form field changes.
   */
  onChange?: (e: React.ChangeEvent<HTMLSelectElement>) => void;
  /**
   * Specifies if the form field is required.
   */
  required?: boolean;

  /**
   * Specifies if the form field should include an empty option.
   */
  includeEmptyOption?: boolean;
}

/**
 * Select form field component.
 *
 * Reads and updates the WP data available in the WPOptionsProvider.
 * @returns A select form field component.
 */
export function SelectFormField({
  name,
  titleKey,
  labelKey,
  descriptionKey,
  options,
  onChange,
  required = false,
  includeEmptyOption = false
}: SelectFormFieldProps): JSX.Element {
  const { getOption, setOption } = useWP();

  return (
    <WPFormField>
      <WPLabel htmlFor={name}>{gettext(titleKey)}</WPLabel>
      <div className="vipps-mobilepay-react-col">
        <WPSelect
          id={name}
          name={name}
          onChange={(e) => {
            setOption(name, e.target.value);
            if (onChange) onChange(e);
          }}
          required={required}
          value={getOption(name)}
        >
          {includeEmptyOption && <WPOption value=""></WPOption>}
          {options.map((option) => (
            <WPOption key={option.value} value={option.value}>
              {option.label}
            </WPOption>
          ))}
        </WPSelect>
        <div>{labelKey && <UnsafeHtmlText htmlString={gettext(labelKey)} />}</div>
        {descriptionKey && <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={gettext(descriptionKey)} />}
      </div>
    </WPFormField>
  );
}

/**
 * Represents the props for an input form field.
 */
interface InputFormFieldProps {
  /**
   * The name of the form field.
   */
  name: string;
  /**
   * The key for the title of the form field.
   */
  titleKey: string;
  /**
   * The key for the label of the form field.
   */
  labelKey?: string;
  /**
   * The key for the description of the form field.
   */
  descriptionKey?: string;
  /**
   * The pattern for the form field value.
   */
  pattern?: string;
  /**
   * Specifies if the form field is required.
   */
  required?: boolean;
  /**
   * Specifies if the form field value should be hidden when not focused.
   */
  asterisk?: boolean;
  /**
   * The type of the form field.
   */
  type?: string;
}

/**
 * Input form field component.
 *
 * Reads and updates the WP data available in the WPOptionsProvider.
 * @returns An input form field component.
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
}: InputFormFieldProps): JSX.Element {
  const { getOption, setOption } = useWP();
  const [isFocused, setIsFocused] = useState(false);
  const shouldHideValue = !isFocused && asterisk;

  return (
    <WPFormField>
      <WPLabel htmlFor={name}>{gettext(titleKey)}</WPLabel>
      <div className="vipps-mobilepay-react-col">
        <WPInput
          id={name}
          name={name}
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

/**
 * Props for the TextareaFormField component.
 */
interface TextareaFormFieldProps {
  /**
   * The name of the textarea field.
   */
  name: string;

  /**
   * The key for the title of the textarea field.
   */
  titleKey: string;

  /**
   * The key for the label of the textarea field.
   */
  labelKey?: string;

  /**
   * The key for the description of the textarea field.
   */
  descriptionKey?: string;

  /**
   * The number of rows for the textarea field.
   */
  rows?: number;
}

/**
 * Textarea form field component.
 *
 * Reads and updates the WP data available in the WPOptionsProvider.
 * @returns A textarea form field component.
 */
export function TextareaFormField({ name, titleKey, labelKey, descriptionKey, rows = 5 }: TextareaFormFieldProps): JSX.Element {
  const { getOption, setOption } = useWP();
  return (
    <WPFormField>
      <WPLabel htmlFor={name}>{gettext(titleKey)}</WPLabel>
      <div className="vipps-mobilepay-react-col">
        <WPTextarea id={name} name={name} onChange={(e) => setOption(name, e.target.value)} value={getOption(name)} rows={rows} />
        <div>{labelKey && <UnsafeHtmlText htmlString={gettext(labelKey)} />}</div>
        {descriptionKey && <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={gettext(descriptionKey)} />}
      </div>
    </WPFormField>
  );
}
