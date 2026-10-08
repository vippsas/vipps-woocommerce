import { useState } from 'react';
import { getPageData, gettext } from '../../lib/wp-data';
import { WPButton, WPFormField, WPInput, WPLabel } from '../form-elements';
import { PageShell, PostForm, RichText } from './shared';

type Hook = { id: string; url: string; events: string[]; local: boolean };
type Merchant = { msn: string; testmode: boolean; hooks: Hook[] };
type Data = { merchants: Merchant[]; events: Record<string, Record<string, string>>; defaultEvents: string[] };

export function WebhooksPage() {
  const { merchants, events, defaultEvents } = getPageData<Data>();
  const [view, setView] = useState<Hook | null>(null);
  const [addMsn, setAddMsn] = useState<string | null>(null);
  const [selected, setSelected] = useState<string[]>(defaultEvents);
  const openAdd = (msn: string) => { setSelected(defaultEvents); setAddMsn(msn); };
  const toggle = (event: string) => setSelected(current => current.includes(event) ? current.filter(value => value !== event) : [...current, event]);
  return <PageShell title={gettext('title')}>
    <div className="vipps-settings-panel vipps-extra-page">
      <p><RichText html={gettext('description')} /></p>
      <p>{gettext('automatic')}</p><p>{gettext('other')}</p>
      <p><RichText html={gettext('developer')} /> <RichText html={gettext('limit')} /></p>
      <p>{gettext('listing')}</p>
    </div>
    {merchants.map(merchant => <div className="vipps-settings-panel vipps-extra-page" key={merchant.msn}>
      <div className="vipps-extra-heading"><h2>{gettext('merchant').replace('%1$s', merchant.msn)} {merchant.testmode && `(${gettext('testMode')})`}</h2>
        <WPButton type="button" onClick={() => openAdd(merchant.msn)}>{gettext('addForMsn')}</WPButton></div>
      <div className="vipps-extra-table-wrap"><table className="widefat striped vipps-extra-table"><thead><tr><th>{gettext('webhook')}</th><th>{gettext('action')}</th></tr></thead><tbody>
        {merchant.hooks.map(hook => <tr key={hook.id}><td>{hook.url}</td><td className="vipps-extra-actions">
          <WPButton type="button" variant="link" onClick={() => setView(hook)}>{gettext('view')}</WPButton>
          {hook.local ? <em>{gettext('createdHere')}</em> : <PostForm action="vipps_delete_webhook">
            <input type="hidden" name="webhook_msn" value={merchant.msn} /><input type="hidden" name="webhook_id" value={hook.id} />
            <WPButton variant="link">{gettext('delete')}</WPButton>
          </PostForm>}
        </td></tr>)}
      </tbody></table></div>
    </div>)}
    {view && <div className="vipps-extra-modal-backdrop" onClick={() => setView(null)}><div className="vipps-settings-panel vipps-extra-modal" role="dialog" aria-modal="true" aria-label={gettext('view')} onClick={event => event.stopPropagation()}>
      <dl><dt>ID</dt><dd>{view.id}</dd><dt>URL</dt><dd>{view.url}</dd><dt>Events</dt><dd>{view.events.join(', ')}</dd></dl>
      <WPButton type="button" variant="primary" onClick={() => setView(null)}>{gettext('ok')}</WPButton>
    </div></div>}
    {addMsn && <div className="vipps-extra-modal-backdrop" onClick={() => setAddMsn(null)}><div className="vipps-settings-panel vipps-extra-modal" role="dialog" aria-modal="true" aria-label={gettext('add')} onClick={event => event.stopPropagation()}>
      <h2>{gettext('add')}</h2>
      <PostForm action="vipps_add_webhook">
        <input type="hidden" name="webhook_msn" value={addMsn} /><input type="hidden" name="webhook_events" value={selected.join(',')} />
        <WPFormField><WPLabel htmlFor="vipps-webhook-msn">MSN</WPLabel><WPInput id="vipps-webhook-msn" value={addMsn} readOnly /></WPFormField>
        <WPFormField><WPLabel htmlFor="vipps-webhook-url">URL</WPLabel><WPInput id="vipps-webhook-url" name="webhook_url" type="url" required autoFocus placeholder="https://..." /></WPFormField>
        {Object.entries(events).map(([group, groupEvents]) => <fieldset key={group} className="vipps-extra-choices"><legend>{group}</legend>
          {Object.entries(groupEvents).map(([label, event]) => <label key={event}><input type="checkbox" checked={selected.includes(event)} onChange={() => toggle(event)} /> {label}</label>)}
        </fieldset>)}
        <div className="vipps-extra-actions"><WPButton variant="primary" disabled={!selected.length}>{gettext('addUrl')}</WPButton><WPButton type="button" onClick={() => setAddMsn(null)}>{gettext('cancel')}</WPButton></div>
      </PostForm>
    </div></div>}
  </PageShell>;
}
