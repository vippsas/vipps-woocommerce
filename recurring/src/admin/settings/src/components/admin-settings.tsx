import React, { useRef, useState } from 'react';
import { gettext } from '../lib/wp-data';
import { useHash } from '../hooks/use-hash';
import { useWP } from '../wp-options-provider';
import { WPButton, WPForm } from './form-elements';
import { NotificationBanner, NotificationBannerProps } from './notification-banner';
import { OptionsFormField } from './options-form-field';
import { SettingsTab, Tabs } from './tabs';

/** The Login/Payment settings shell, fed by this gateway's own field definitions. */
export function AdminSettings(): JSX.Element {
  const { settings, isDirty, submitChanges } = useWP();
  const [activeTab, setActiveTab] = useHash('general');
  const [isLoading, setIsLoading] = useState(false);
  const [banner, setBanner] = useState<NotificationBannerProps | null>(null);
  const saving = useRef(false);
  const notice = useRef<HTMLDivElement>(null);
  const tabs: SettingsTab[] = settings.sections.map((section) => ({ id: section.id, title: section.title, fields: Object.keys(section.fields) }));
  const selected = settings.sections.find((section) => section.id === activeTab) ?? settings.sections[0];

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

  return <div className="vipps-settings-shell">
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
            <fieldset className="vipps-recurring-fields">
              {Object.entries(selected.fields).map(([key, field]) => <OptionsFormField key={key} name={key} field={field} />)}
            </fieldset>
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
