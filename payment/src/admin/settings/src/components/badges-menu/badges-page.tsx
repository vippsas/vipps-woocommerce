import { useState } from "react";
import { useAdminPost } from "../../hooks/use-admin-post";
import { NotificationBanner } from "../notification-banner";
import { getMetadata, gettext, getPageData } from "../../lib/wp-data";
import {
  WPButton,
  WPFormField,
  WPLabel,
  WPSelect,
  WPSwitchToggle,
} from "../form-elements";
import { PageShell, PostForm, RichText } from "../page-shell";

type BadgeData = {
  options: { badgeon?: number; defaultall?: number; variant?: string };
  brand: string;
  language: string;
  variants: Record<string, string>;
};

export function BadgesPage() {
  const { options, brand, language, variants } = getPageData<BadgeData>();
  const { submit, pending, banner } = useAdminPost();
  const [enabled, setEnabled] = useState(!!options.badgeon);
  const [defaultAll, setDefaultAll] = useState(!!options.defaultall);
  const [variant, setVariant] = useState(options.variant ?? "");
  return (
    <PageShell
      companyName={getMetadata("company_name") ?? "Vipps MobilePay"}
      subtitle={gettext("title")}
      paymentMethod={getMetadata("payment_method") ?? ""}
    >
      {banner && (
        <div className="vipps-admin-notices" role="status">
          <NotificationBanner {...banner} />
        </div>
      )}
      <div className="vipps-admin-content">
        <div className="vipps-admin-panel">
          <p className="vipps-admin-prose">
            <RichText html={gettext("intro")} />
          </p>
          <p className="vipps-admin-prose">{gettext("description")}</p>
          <h2 className="vipps-admin-panel-title">{gettext("settings")}</h2>
          <PostForm action="update_vipps_badge_settings" disabled={pending !== null} onSubmit={async (event) => {
            event.preventDefault();
            const data = await submit<{ options: BadgeData['options'] }>(event.currentTarget);
            if (data) {
              setEnabled(!!data.options.badgeon);
              setDefaultAll(!!data.options.defaultall);
              setVariant(data.options.variant ?? '');
            }
          }}>
            <input type="hidden" name="badgeon" value={enabled ? "1" : "0"} />
            <input
              type="hidden"
              name="defaultall"
              value={defaultAll ? "1" : "0"}
            />
            <WPFormField className="vipps-mobilepay-react-switch-field">
              <WPSwitchToggle
                id="vipps-badge-enabled"
                checked={enabled ? "yes" : "no"}
                onChange={(value) => setEnabled(value === "yes")}
              />
              <div className="vipps-mobilepay-react-switch-info">
                <WPLabel htmlFor="vipps-badge-enabled">
                  {gettext("enabled")}
                </WPLabel>
              </div>
            </WPFormField>
            <WPFormField className="vipps-mobilepay-react-switch-field">
              <WPSwitchToggle
                id="vipps-badge-default-all"
                checked={defaultAll ? "yes" : "no"}
                onChange={(value) => setDefaultAll(value === "yes")}
              />
              <div className="vipps-mobilepay-react-switch-info">
                <WPLabel htmlFor="vipps-badge-default-all">
                  {gettext("defaultAll")}
                </WPLabel>
                <p className="vipps-admin-prose">{gettext("defaultAllHelp")}</p>
              </div>
            </WPFormField>
            <div className="vipps-admin-preview">
              <vipps-mobilepay-badge
                brand={brand}
                language={language}
                variant={variant || undefined}
              ></vipps-mobilepay-badge>
            </div>
            <WPFormField>
              <WPLabel htmlFor="vipps-badge-variant">
                {gettext("variant")}
              </WPLabel>
              <WPSelect
                id="vipps-badge-variant"
                name="variant"
                value={variant}
                onChange={(event) => setVariant(event.target.value)}
              >
                <option value="">{gettext("chooseVariant")}</option>
                {Object.entries(variants).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </WPSelect>
            </WPFormField>
            <div className="vipps-mobilepay-react-save-section vipps-admin-save-section--spaced">
              <WPButton variant="primary" isLoading={pending !== null}>{gettext("save_changes")}</WPButton>
            </div>
          </PostForm>
        </div>
      </div>
    </PageShell>
  );
}
