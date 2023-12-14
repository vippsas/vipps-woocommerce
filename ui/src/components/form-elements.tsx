import { ComponentProps, PropsWithChildren } from 'react';

export function WPInput(props: ComponentProps<'input'>) {
  return (
    <input {...props} className={['input-text regular-input', props.className ?? ''].join(' ')} style={{ height: 30 }}>
      {props.children}
    </input>
  );
}

export function WPForm(props: ComponentProps<'form'>) {
  return (
    <form {...props} className={['', props.className ?? ''].join(' ')}>
      {props.children}
    </form>
  );
}

export function WPLabel(props: ComponentProps<'label'>) {
  return (
    <label {...props} className={['vipps-mobilepay-react-label', props.className ?? ''].join(' ')}>
      {props.children}
    </label>
  );
}

interface WPButton extends ComponentProps<'button'> {
  variant?: 'primary' | 'secondary' | 'link';
}
export function WPButton({ variant, ...restProps }: WPButton) {
  return (
    <button {...restProps} className={[`button-${variant}`, restProps.className ?? ''].join(' ')}>
      {restProps.children}
    </button>
  );
}

interface WPCheckboxProps {
  name?: string;
  id?: string;
  checked: string | undefined;
  onChange: (value: string) => void;
  className?: string;
}
export function WPCheckbox({ id, name, onChange, checked, children, className }: PropsWithChildren<WPCheckboxProps>) {
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

export function WPSelect(props: ComponentProps<'select'>) {
  return (
    <select {...props} className={[props.className ?? ''].join(' ')}>
      {props.children}
    </select>
  );
}

export function WPOption(props: ComponentProps<'option'>) {
  return (
    <option {...props} className={[props.className ?? ''].join(' ')}>
      {props.children}
    </option>
  );
}

export function WPTextarea(props: ComponentProps<'textarea'>) {
  return (
    <textarea {...props} className={[props.className ?? ''].join(' ')}>
      {props.children}
    </textarea>
  );
}

export function WPFormField(props: ComponentProps<'div'>) {
  return (
    <div {...props} className={['vipps-mobilepay-react-form-field', props.className ?? ''].join(' ')}>
      {props.children}
    </div>
  );
}

function truthToBool(value: string | null) {
  return value === 'yes';
}
function boolToTruth(value: boolean) {
  return value ? 'yes' : 'no';
}
