import { useState } from 'react';
import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { Collapsible } from '../collapsible';
import { truthToBool } from '../form-elements';
import { SwitchFormField, SelectFormField } from '../options-form-fields';

/**
 * A React component that renders the express options tab for the admin settings page.
 *
 * @returns The rendered express options tab.
 */
export function AdminSettingsExpressOptionsTab(): JSX.Element {
  const { getOption, setOption } = useWP();
  const expressOverrides = [
    'cartexpress',
    'express_show_in_checkout',
    'singleproductexpressarchives',
    'express_singleproduct_enabled'
  ];

  function updateMasterFromOverride(changedOption: string, value: string) {
    const anyEnabled = expressOverrides.some(option =>
      truthToBool(option === changedOption ? value : getOption(option))
    );
    setOption('express_enabled', anyEnabled ? 'yes' : 'no');
  }
  const expressEnabled = truthToBool(getOption('cartexpress'))
    || truthToBool(getOption('express_show_in_checkout'))
    || truthToBool(getOption('singleproductexpressarchives'))
    || getOption('singleproductexpress') !== 'none';

  const [expressEnableShowMore, setExpressEnableShowMore] = useState(false);

  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('express_options.description')}</p>

      {/* Master toggle for express on/off. LP 2026-09-28 */}
      <SwitchFormField
        name="express_enabled"
        titleKey="express_enabled.title"
        labelKey="express_enabled.label"
        descriptionKey="express_enabled.description"
        onChange={(value) => expressOverrides.forEach(option => setOption(option, value))}
        trailingAction={
          <button
            type="button"
            className="vipps-mobilepay-react-overrides-toggle"
            aria-label={gettext('express_advanced_placement')}
            title={gettext('express_advanced_placement')}
            aria-expanded={expressEnableShowMore}
            aria-controls="vipps-mobilepay-react-express-overrides"
            onClick={() => setExpressEnableShowMore((showMore) => !showMore)}
          >
            <span className="vipps-mobilepay-react-overrides-chevron" aria-hidden="true" />
          </button>
        }
      />

      {/* Context/page specific overrides for express. LP 2026-09-28 */}
      <div id="vipps-mobilepay-react-express-overrides" className="vipps-mobilepay-react-express-overrides" hidden={!expressEnableShowMore}>
        <div className="vipps-mobilepay-react-express-overrides-heading">{gettext('express_advanced_placement')}</div>
        {expressOverrides.map(option => (
          <SwitchFormField
            key={option}
            name={option}
            titleKey={`${option}.title`}
            labelKey={`${option}.label`}
            descriptionKey={`${option}.description`}
            onChange={(value) => updateMasterFromOverride(option, value)}
          />
        ))}
      </div>


      {/* Dont show the rest of the options unless express is on. LP 2026-09-28 */ }

      {/* Renders a select field that allows an admin to specify which products should have the "express checkout" option enabled  */}
      <SelectFormField
        name="singleproductexpress"
        titleKey="singleproductexpress.title"
        descriptionKey="singleproductexpress.description"
        options={[
          { value: 'none', label: gettext('singleproductexpress.options.none') },
          { value: 'some', label: gettext('singleproductexpress.options.some') },
          { value: 'all', label: gettext('singleproductexpress.options.all') }
        ]}
      />

      {/* Toggle for whether or not new users should be created when using Express Checkout */}
      <SwitchFormField
        name="expresscreateuser"
        titleKey="expresscreateuser.title"
        labelKey="expresscreateuser.label"
        descriptionKey="expresscreateuser.description"
      />


      {/* Only show the rest of the options if express is actually enabled (the options above). LP 2026-09-25 */}
      {expressEnabled && (<>
        {/* Shipping section */ }
        <Collapsible title={gettext('express_shipping_section')}>
          {/* Toggle for whether or not the users should always be asked for an address */}
          <SwitchFormField
            name="expresscheckout_always_address"
            titleKey="expresscheckout_always_address.title"
            labelKey="expresscheckout_always_address.label"
            descriptionKey="expresscheckout_always_address.description"
          />

          {/* Toggle for static shipping for Express Checkout */}
          <SwitchFormField
            name="enablestaticshipping"
            titleKey="enablestaticshipping.title"
            labelKey="enablestaticshipping.label"
            descriptionKey="enablestaticshipping.description"
          />
        </Collapsible>

        {/* Advanced section */ }
        <Collapsible title={gettext('express_advanced_section')}>
          {/* Toggle for whether or not users should be asked if they've read the store's terms and conditions */}
          <SwitchFormField
            name="expresscheckout_termscheckbox"
            titleKey="expresscheckout_termscheckbox.title"
            labelKey="expresscheckout_termscheckbox.label"
            descriptionKey="expresscheckout_termscheckbox.description"
          />

          {/* Toggle for whether or not failed Express Checkout orders should be deleted */}
          <SwitchFormField
            name="deletefailedexpressorders"
            titleKey="deletefailedexpressorders.title"
            labelKey="deletefailedexpressorders.label"
            descriptionKey="deletefailedexpressorders.description"
          />

          {/* Toggle for compatibility mode for the "Buy now" button */}
          <SwitchFormField
            name="singleproductbuynowcompatmode"
            titleKey="singleproductbuynowcompatmode.title"
            labelKey="singleproductbuynowcompatmode.label"
            descriptionKey="singleproductbuynowcompatmode.description"
          />
        </Collapsible>
      </>)}
    </div>
  );
}
