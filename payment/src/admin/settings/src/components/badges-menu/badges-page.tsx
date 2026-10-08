import { useState } from 'react';
import { gettext, getPageData } from '../../lib/wp-data';
import { WPButton, WPFormField, WPLabel, WPSelect, WPSwitchToggle } from '../form-elements';
import { PageShell, PostForm, RichText } from '../page-shell';

type BadgeData = { options: { badgeon?: number; defaultall?: number; variant?: string }; brand: string; language: string; variants: Record<string, string> };

export function BadgesPage() {
  const { options, brand, language, variants } = getPageData<BadgeData>();
  const [enabled, setEnabled] = useState(!!options.badgeon);
  const [defaultAll, setDefaultAll] = useState(!!options.defaultall);
  const [variant, setVariant] = useState(options.variant ?? '');
  return <PageShell title={gettext('title')}>
    <div className="vipps-settings-panel vipps-extra-page">
      <p><RichText html={gettext('intro')} /></p><p>{gettext('description')}</p>
      <h2>{gettext('settings')}</h2>
      <PostForm action="update_vipps_badge_settings">
        <input type="hidden" name="badgeon" value={enabled ? '1' : '0'} />
        <input type="hidden" name="defaultall" value={defaultAll ? '1' : '0'} />
        <WPFormField className="vipps-mobilepay-react-switch-field">
          <WPSwitchToggle id="vipps-badge-enabled" checked={enabled ? 'yes' : 'no'} onChange={value => setEnabled(value === 'yes')} />
          <div className="vipps-mobilepay-react-switch-info"><WPLabel htmlFor="vipps-badge-enabled">{gettext('enabled')}</WPLabel></div>
        </WPFormField>
        <WPFormField className="vipps-mobilepay-react-switch-field">
          <WPSwitchToggle id="vipps-badge-default-all" checked={defaultAll ? 'yes' : 'no'} onChange={value => setDefaultAll(value === 'yes')} />
          <div className="vipps-mobilepay-react-switch-info"><WPLabel htmlFor="vipps-badge-default-all">{gettext('defaultAll')}</WPLabel><p>{gettext('defaultAllHelp')}</p></div>
        </WPFormField>
        <div className="vipps-extra-preview"><vipps-mobilepay-badge brand={brand} language={language} variant={variant || undefined}></vipps-mobilepay-badge></div>
        <WPFormField><WPLabel htmlFor="vipps-badge-variant">{gettext('variant')}</WPLabel>
          <WPSelect id="vipps-badge-variant" name="variant" value={variant} onChange={event => setVariant(event.target.value)}>
            <option value="">{gettext('chooseVariant')}</option>
            {Object.entries(variants).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </WPSelect>
        </WPFormField>
        <div className="vipps-mobilepay-react-save-section"><WPButton variant="primary">{gettext('update')}</WPButton></div>
      </PostForm>
    </div>
    <div className="vipps-settings-panel vipps-extra-page"><h2>{gettext('block')}</h2><p>{gettext('blockHelp')}</p>
      <h2>{gettext('shortcodes')}</h2><p><RichText html={gettext('shortcodeHelp')} /></p>
      <p>{gettext('shortcodeIntro')}</p><pre>[vipps-mobilepay-badge variant={'{white|filled|light|grey|purple}'}<br /> language={'{en|no|fi|da|sv}'} ]</pre>
      <p>{gettext('shortcodeDocs')} {gettext('brandAutomatic')}</p>
    </div>
  </PageShell>;
}
