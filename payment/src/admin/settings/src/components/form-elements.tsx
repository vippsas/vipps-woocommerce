import { ComponentProps, PropsWithChildren } from 'react';
import { LoadingSpinner } from './loading-spinner';

/**
 * Renders an input element with custom styling.
 *
 * @returns The rendered input element.
 */
export function WPInput(props: ComponentProps<'input'>): JSX.Element {
  return (
    <input {...props} className={['input-text regular-input', props.className ?? ''].join(' ')} style={{ height: 30 }}>
      {props.children}
    </input>
  );
}

/**
 * Renders a form element with the provided props.
 *
 * @returns The rendered form element.
 */
export function WPForm(props: ComponentProps<'form'>): JSX.Element {
  return (
    <form {...props} className={['', props.className ?? ''].join(' ')}>
      {props.children}
    </form>
  );
}

/**
 * Renders a label element with custom styling.
 *
 * @returns The rendered label element.
 */
export function WPLabel(props: ComponentProps<'label'>): JSX.Element {
  return (
    <label {...props} className={['vipps-mobilepay-react-label', props.className ?? ''].join(' ')}>
      {props.children}
    </label>
  );
}

/**
 * Represents a button component with additional properties.
 * @returns The rendered button element.
 */
interface WPButton extends ComponentProps<'button'> {
  /**
   * The variant of the button, these follow WordPress's default styles.
   */
  variant?: 'primary' | 'secondary' | 'link';

  isLoading?: boolean;
}
/**
 * Renders a custom button component for the WPButton.
 * @returns The rendered button component.
 */
export function WPButton({ variant, isLoading, disabled, ...restProps }: WPButton): JSX.Element {
  return (
    <button {...restProps} disabled={isLoading || disabled} className={[`button-${variant}`, restProps.className ?? ''].join(' ')}>
      <div style={{ display: 'flex', alignItems: 'center' }}>
        {isLoading && <LoadingSpinner />}
        <span>{restProps.children}</span>
      </div>
    </button>
  );
}

/**
 * Represents the props for a WPCheckbox component.
 */
interface WPCheckboxProps {
  /**
   * The name of the checkbox.
   */
  name?: string;
  /**
   * The ID of the checkbox.
   */
  id?: string;
  /**
   * The checked state of the checkbox.
   */
  checked: string | undefined;
  /**
   * The callback function to be called when the checkbox value changes.
   * @param value - The new value of the checkbox.
   */
  onChange: (value: string) => void;
  /**
   * The CSS class name for the checkbox.
   */
  className?: string;
}

/**
 * Renders a checkbox input component.
 * @returns The rendered checkbox element.
 */
export function WPCheckbox({ id, name, onChange, checked, children, className }: PropsWithChildren<WPCheckboxProps>): JSX.Element {
  return (
    <input
      id={id}
      name={name}
      checked={checked ? truthToBool(checked) : undefined}
      onChange={(e) => onChange(boolToTruth(e.target.checked))}
      type="checkbox"
      className={[className ?? ''].join(' ')}
    >
      {children}
    </input>
  );
}

/**
 * Represents the props for a WPSelect component.
 * @returns The rendered select element.
 */
interface WPSelectProps extends ComponentProps<'select'> {
  error?: string;
}

export function WPSelect({ error, ...props }: WPSelectProps): JSX.Element {
  return (
    <div>
      <select {...props} className={['', props.className ?? '', error ? 'has-error' : ''].join(' ')}>
        {props.children}
      </select>
      {error && (
        <div className="vipps-mobilepay-react-field-error">
          {error.split('\n').map((line, i) => (
            <div key={i}>{line}</div>
          ))}
        </div>
      )}
    </div>
  );
}

/**
 * Represents the props for a WPCheckbox component.
 * @returns The rendered option element.
 */
export function WPOption(props: ComponentProps<'option'>) {
  return (
    <option {...props} className={[props.className ?? ''].join(' ')}>
      {props.children}
    </option>
  );
}

/**
 * Represents the props for a WPTextarea component.
 * @returns The rendered textarea element.
 */
export function WPTextarea(props: ComponentProps<'textarea'>) {
  return (
    <textarea {...props} className={[props.className ?? ''].join(' ')}>
      {props.children}
    </textarea>
  );
}

/**
 * Represents the props for a WPFormField component.
 * @returns The rendered form field container.
 */
export function WPFormField(props: ComponentProps<'div'>) {
  return (
    <div {...props} className={['vipps-mobilepay-react-form-field', props.className ?? ''].join(' ')}>
      {props.children}
    </div>
  );
}

/**
 * Converts a string value to a boolean value based on the WordPress expectation of 'yes' or 'no'.
 * @param value - The string value to convert.
 * @returns The boolean representation of the value.
 */
export function truthToBool(value: string | null) {
  return value === 'yes';
}

/**
 * Converts a boolean value to a string value based on the WordPress expectation of 'yes' or 'no'.
 * @param value - The boolean value to convert.
 * @returns The string representation of the value.
 */
export function boolToTruth(value: boolean) {
  return value ? 'yes' : 'no';
}

/**
 * Inverts a string truth value to its opposite based on the WordPress expectation of 'yes' or 'no'.
 * @param value - The string value to convert.
 * @returns 
 */
export function invertTruth(value: string | null ) {
  return truthToBool(value) ? 'no' : 'yes';
}
