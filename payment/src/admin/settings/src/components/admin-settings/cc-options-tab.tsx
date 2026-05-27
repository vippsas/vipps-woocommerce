import { CheckboxFormField,  TextareaFormField } from '../options-form-fields';
import { gettext } from '../../lib/wp-data';

/**
 * A React component that renders the main options tab for the admin settings page.
 *
 * @returns The rendered main options tab.
 */
export function AdminSettingsCCOptionsTab(): JSX.Element {
  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('cc_options.description')}</p>

      {/* Renders a checkbox that specifies whether or not the plugin is enabled  */}
      <CheckboxFormField name="cc_enabled" titleKey="cc_enabled.title" labelKey="cc_enabled.label" />

      {/* Renders a textarea field for the description */}
      <TextareaFormField name="cc_description" titleKey="cc_description.title" descriptionKey="cc_description.description" rows={5} />

    </div>
  );
}
