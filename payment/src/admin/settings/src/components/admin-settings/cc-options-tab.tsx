import { CheckboxFormField,  TextareaFormField } from '../options-form-fields';
import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { UnsafeHtmlText } from '../unsafe-html-text';
import { truthToBool } from '../form-elements';

/**
 * A React component that renders the main options tab for the admin settings page.
 *
 * @returns The rendered main options tab.
 */
export function AdminSettingsCCOptionsTab(): JSX.Element {
  const { getOption } = useWP();

  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('cc_options.description')}</p>

      {/* Warning about possible unavailability in test environment if test mode is enabled. LP 2026-05-28 */}
      {truthToBool(getOption('testmode')) && (
        <div style={{border: "2px solid red", padding:".5rem", "marginBottom": "2rem"}}>
          <p><UnsafeHtmlText htmlString={gettext("cc_options.test_mode_warning")}></UnsafeHtmlText></p>
        </div>
      )}

      {/* Renders a checkbox that specifies whether or not the plugin is enabled  */}
      <CheckboxFormField name="cc_enabled" titleKey="cc_enabled.title" labelKey="cc_enabled.label" />

      {/* Renders a textarea field for the description */}
      <TextareaFormField name="cc_description" titleKey="cc_description.title" descriptionKey="cc_description.description" rows={5} />

    </div>
  );
}
