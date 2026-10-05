import React, { useRef, useState } from 'react';
import { gettext } from '../lib/wp-data';
import { useHash } from '../hooks/use-hash';
import { useWP } from '../wp-options-provider';
import { WPButton, WPForm } from './form-elements';
import { NotificationBanner, NotificationBannerProps } from './notification-banner';
import { OptionsFormField } from './options-form-field';
import { SettingsTab, Tabs } from './tabs';
import { Collapsible } from './collapsible';

/** The Login/Payment settings shell, fed by this gateway's own field definitions. */
export function AdminSettings(): JSX.Element {
  const { settings, values, isDirty, submitChanges } = useWP();
  const [activeTab, setActiveTab] = useHash('general');
  const [isLoading, setIsLoading] = useState(false);
  const [banner, setBanner] = useState<NotificationBannerProps | null>(null);
  const saving = useRef(false);
  const notice = useRef<HTMLDivElement>(null);
  // The brand option updates in provider state as soon as its select changes.
  const brandClass = values.brand === 'mobilepay' ? 'MobilePay' : 'Vipps';
  // Match Payment and Login: keep the enable switch and API keys available
  // while the gateway is disabled, and reveal the other tabs immediately
  // when an administrator turns it back on.
  const gatewayEnabled = values.enabled === 'yes';
  const visibleSections = settings.sections.filter((section) => gatewayEnabled || ['general', 'keys'].includes(section.id));
  const tabs: SettingsTab[] = visibleSections.map((section) => ({ id: section.id, title: section.title, fields: Object.keys(section.fields) }));
  const selected = visibleSections.find((section) => section.id === activeTab) ?? visibleSections[0];
  const visibleFields = Object.entries(selected.fields).filter(([key]) => gatewayEnabled || selected.id !== 'general' || key === 'enabled');
  const productionKeys = visibleFields.filter(([key]) => !key.startsWith('test_'));
  const testKeys = visibleFields.filter(([key]) => key.startsWith('test_'));

  async function handleSave(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (saving.current) return;
    saving.current = true;
    setIsLoading(true);
    setBanner(null);
    try {
      const result = await submitChanges();
      if (!result.saved || result.form_errors?.length) {
        setBanner({ variant: 'error', text: result.form_errors?.length ? result.form_errors : gettext('save_failed') });
      } else if (!result.connection_ok) {
        setBanner({ variant: 'warning', text: gettext('connection_failed') });
      } else {
        setBanner({ variant: 'success', text: gettext('settings_saved') });
      }
    } catch (error) {
      setBanner({ variant: 'error', text: error instanceof Error ? error.message : gettext('save_failed') });
    } finally {
      saving.current = false;
      setIsLoading(false);
      window.requestAnimationFrame(() => notice.current?.focus());
    }
  }

  return <div className={`vipps-settings-shell ${brandClass}`}>
    <header className="vipps-settings-header"><h1>Vipps MobilePay</h1><p className="vipps-recurring-subtitle">{gettext('page_title')}</p></header>
    {banner && <div className="vipps-settings-notices" role="status" tabIndex={-1} ref={notice}>
      <NotificationBanner {...banner} />
    </div>}
    <WPForm onSubmit={handleSave} className="vippsAdminSettings" aria-busy={isLoading}
      onChange={() => setBanner(null)}>
      <div className="vipps-settings-layout">
        <nav className="vipps-settings-navigation" aria-label={gettext('page_title')}>
          <Tabs tabs={tabs} activeTab={selected.id} onTabChange={setActiveTab} />
        </nav>
        <div className="vipps-settings-content">
          <section className="vipps-settings-panel" id="vipps-recurring-tab-panel" role="tabpanel"
            aria-labelledby={`vipps-recurring-tab-${selected.id}`} tabIndex={0}>
            <h2>{selected.title}</h2>
            {selected.description && <p className="vipps-mobilepay-react-tab-description" dangerouslySetInnerHTML={{ __html: selected.description }} />}
            {selected.id === 'keys' ? <div>
              <Collapsible title={gettext('production_keys_section')}>
                <fieldset className="vipps-recurring-fields">
                  {productionKeys.map(([key, field]) => <OptionsFormField key={key} name={key} field={field} />)}
                </fieldset>
              </Collapsible>
              {testKeys.length > 0 && <Collapsible title={gettext('test_keys_section')}>
                <fieldset className="vipps-recurring-fields">
                  {testKeys.map(([key, field]) => <OptionsFormField key={key} name={key} field={field} />)}
                </fieldset>
              </Collapsible>}
            </div> : <fieldset className="vipps-recurring-fields">
              {visibleFields.map(([key, field]) => <OptionsFormField key={key} name={key} field={field} />)}
            </fieldset>}
          </section>
          <div className="vipps-mobilepay-react-save-section">
            {isDirty && <span className="vipps-recurring-unsaved">{gettext('unsaved_changes')}</span>}
            <WPButton type="submit" variant="primary" isLoading={isLoading}>{gettext('save_changes')}</WPButton>
          </div>
        </div>
      </div>
    </WPForm>
  </div>;
}
