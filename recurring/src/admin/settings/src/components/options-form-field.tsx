import { FormField, gettext } from '../lib/wp-data';
import { useWP } from '../wp-options-provider';
import { WPFormField, WPInput, WPLabel, WPOption, WPSelect } from './form-elements';
import { UnsafeHtmlText } from './unsafe-html-text';

interface Props { name: string; field: FormField }

/** Render the field type supplied by the Recurring gateway's WooCommerce schema. */
export function OptionsFormField({ name, field }: Props): JSX.Element {
  const { settings, values, setOption } = useWP();
  const value = String(values[name] ?? field.default ?? '');
  const id = `vipps-recurring-${name}`;
  const description = field.description && <div id={`${id}-description`} className="vipps-mobilepay-react-secondary-label">
    <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={field.description} />
  </div>;
  const supported = ['checkbox', 'text', 'password', 'number', 'select', 'page_dropdown'].includes(field.type);

  if (!supported) {
    return <WPFormField><strong>{field.title}</strong><div>{gettext('unsupported_field')}{' '}
      <a href={settings.native_url}>{gettext('open_woocommerce')}</a></div></WPFormField>;
  }

  if (field.type === 'checkbox') {
    return <WPFormField className="vipps-mobilepay-react-switch-field">
      <input id={id} name={name} type="checkbox" className="vipps-mobilepay-react-switch"
        checked={value === 'yes'} disabled={field.disabled}
        aria-describedby={description ? `${id}-description` : undefined}
        onChange={(event) => setOption(name, event.target.checked ? 'yes' : 'no')} />
      <div className="vipps-mobilepay-react-switch-info">
        <WPLabel htmlFor={id}>{field.label || field.title}</WPLabel>{description}
      </div>
    </WPFormField>;
  }

  return <WPFormField>
    <WPLabel htmlFor={id}>{field.title}</WPLabel>
    <div className="vipps-mobilepay-react-col">
      {field.type === 'select' || field.type === 'page_dropdown' ? (
        <WPSelect id={id} name={name} value={field.type === 'page_dropdown' && value === '' ? '-1' : value} disabled={field.disabled}
          aria-describedby={description ? `${id}-description` : undefined}
          onChange={(event) => setOption(name, event.target.value)}>
          {Object.entries(field.options).map(([key, label]) => <WPOption key={key} value={key}>{label}</WPOption>)}
        </WPSelect>
      ) : (
        <WPInput id={id} name={name} type={field.type} value={value} disabled={field.disabled}
          autoComplete={field.type === 'password' ? 'off' : undefined}
          spellCheck={field.type === 'password' ? false : undefined}
          aria-describedby={description ? `${id}-description` : undefined}
          onFocus={(event) => { if (field.type === 'password') event.currentTarget.type = 'text'; }}
          onBlur={(event) => { if (field.type === 'password') event.currentTarget.type = 'password'; }}
          onChange={(event) => setOption(name, event.target.value)} />
      )}
      {description}
    </div>
  </WPFormField>;
}
