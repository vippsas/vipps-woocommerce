import { useState } from "react";
import { useAdminPost } from "../../hooks/use-admin-post";
import { NotificationBanner } from "../notification-banner";
import { getMetadata, getPageData, gettext } from "../../lib/wp-data";
import { WPButton, WPFormField, WPLabel, WPSelect } from "../form-elements";
import { PageShell, PostForm } from "../page-shell";
import { Tabs } from "../tabs";
import { useHash } from "../../hooks/use-hash";

type Config = Record<string, string | boolean>;
type Data = {
  configs: Record<string, Config>;
  defaults: Config;
  brand: string;
  language: string;
  context: string;
  isMobilePay: boolean;
};
const contexts = [
  "global",
  "product",
  "catalog",
  "cart",
  "minicart",
  "checkout",
];
const booleanKeys = ["rounded", "compact", "stretched"];
const choices: Record<string, string[]> = {
  language: ["store", "en", "no", "dk", "sv", "fi"],
  verb: ["buy", "pay", "continue", "confirm", "donate", "express"],
  variant: ["primary", "dark", "light"],
};
const labels: Record<string, string> = {
  language: "languageLabel",
  express: "expressVerb",
};
const boolValue = (value: string | boolean | undefined) =>
  value === true || value === "true" || value === "1";
const editableKeys = [...booleanKeys, ...Object.keys(choices)];
const editableConfig = (config: Config): Config =>
  Object.fromEntries(
    editableKeys
      .map((key) => [key, config[key]])
      .filter(([, value]) => value !== undefined),
  );

export function ButtonsPage() {
  const {
    configs: initial,
    defaults,
    brand,
    language,
    context: initialContext,
    isMobilePay,
  } = getPageData<Data>();
  const { submit, pending, banner } = useAdminPost();
  const [configs, setConfigs] = useState<Record<string, Config>>(() => ({
    ...initial,
  }));
  const [context, setContext] = useState(
    contexts.includes(initialContext) ? initialContext : "global",
  );
  const expressTab = gettext("express");
  const tabs = [expressTab];
  const [activeTab, setActiveTab] = useHash(expressTab);
  const selectedTab = tabs.includes(activeTab) ? activeTab : expressTab;
  const inherited =
    context !== "global" &&
    (!configs[context] || boolValue(configs[context]["use-global-config"]));
  const globalConfig = { ...defaults, ...configs.global };
  const config = inherited ? globalConfig : { ...defaults, ...configs[context] };
  const update = (key: string, value: string | boolean) =>
    setConfigs((previous) => ({
      ...previous,
      [context]: {
        ...editableConfig({ ...defaults, ...previous[context] }),
        [key]: value,
      },
    }));
  const toggleInheritance = (checked: boolean) =>
    setConfigs((previous) => ({
      ...previous,
      [context]: checked
        ? { "use-global-config": true }
        : editableConfig(globalConfig),
    }));
  const previewLanguage =
    config.language === "store"
      ? language
      : String(config.language ?? language);
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
      <PostForm action="update_vipps_button_settings" disabled={pending !== null} onSubmit={async (event) => {
        event.preventDefault();
        const data = await submit<{ configs: Record<string, Config> }>(event.currentTarget);
        if (data) setConfigs(data.configs);
      }}>
        <div className="vipps-admin-layout">
          <nav className="vipps-admin-navigation" aria-label={gettext("title")}>
            <Tabs
              tabs={tabs}
              onTabChange={setActiveTab}
              activeTab={selectedTab}
              idPrefix="vipps-buttons"
            />
          </nav>
          <div className="vipps-admin-content">
            <section
              className="vipps-admin-panel"
              id="vipps-buttons-tab-panel"
              role="tabpanel"
              aria-labelledby={`vipps-buttons-tab-${tabs.indexOf(selectedTab)}`}
              tabIndex={0}
            >
              <input type="hidden" name="express-context" value={context} />
              {Object.entries({
                ...configs,
                [context]:
                  configs[context] ??
                  (context === "global"
                    ? editableConfig(globalConfig)
                    : { "use-global-config": true }),
              }).flatMap(([ctx, settings]) =>
                Object.entries(
                  boolValue(settings["use-global-config"])
                    ? { "use-global-config": true }
                    : editableConfig(settings),
                ).map(([key, value]) => (
                  <input
                    key={`${ctx}-${key}`}
                    type="hidden"
                    name={`express[configs][${ctx}][${key}]`}
                    value={String(value)}
                  />
                )),
              )}
              <WPFormField>
                <WPLabel htmlFor="vipps-button-context">
                  {gettext("context")}
                </WPLabel>
                <WPSelect
                  id="vipps-button-context"
                  value={context}
                  onChange={(event) => setContext(event.target.value)}
                >
                  {contexts.map((value) => (
                    <option key={value} value={value}>
                      {gettext(value)}
                    </option>
                  ))}
                </WPSelect>
              </WPFormField>
              {context !== "global" && (
                <WPFormField className="vipps-admin-inline">
                  <label>
                    <input
                      type="checkbox"
                      checked={inherited}
                      onChange={(event) =>
                        toggleInheritance(event.target.checked)
                      }
                    />{" "}
                    {gettext("useGlobal")}
                  </label>
                </WPFormField>
              )}
              <div className="vipps-admin-controls" aria-disabled={inherited}>
                <fieldset className="vipps-admin-inline">
                  {booleanKeys.map((key) => (
                    <label key={key}>
                      <input
                        disabled={inherited}
                        type="checkbox"
                        checked={boolValue(config[key])}
                        onChange={(event) => update(key, event.target.checked)}
                      />{" "}
                      {gettext(key)}
                    </label>
                  ))}
                </fieldset>
                {Object.entries(choices).map(([key, values]) => (
                  <fieldset key={key} className="vipps-admin-choices">
                    <legend>{gettext(labels[key] ?? key)}</legend>
                    {values
                      .filter(
                        (value) =>
                          key !== "language" || value !== "fi" || isMobilePay,
                      )
                      .map((value) => (
                        <label key={value}>
                          <input
                            disabled={
                              inherited ||
                              (key === "language" && value === "fi")
                            }
                            type="radio"
                            name={`ui-${key}`}
                            checked={config[key] === value}
                            onChange={() => update(key, value)}
                          />{" "}
                          {gettext(labels[value] ?? value)}
                        </label>
                      ))}
                    {key === "language" && !isMobilePay && (
                      <p className="vipps-admin-prose">
                        {gettext("finnishHelp")}
                      </p>
                    )}
                  </fieldset>
                ))}
              </div>
              <div className="vipps-admin-preview">
                <vipps-mobilepay-button
                  type="button"
                  brand={brand}
                  language={
                    previewLanguage === "dk"
                      ? "da"
                      : previewLanguage === "se"
                      ? "sv"
                      : previewLanguage
                  }
                  variant={String(config.variant ?? "primary")}
                  rounded={String(boolValue(config.rounded))}
                  compact={String(boolValue(config.compact))}
                  stretched={String(boolValue(config.stretched))}
                  verb={String(config.verb ?? "buy")}
                ></vipps-mobilepay-button>
              </div>
            </section>
            <div className="vipps-mobilepay-react-save-section">
              <WPButton variant="primary" isLoading={pending !== null}>{gettext("update")}</WPButton>
            </div>
          </div>
        </div>
      </PostForm>
    </PageShell>
  );
}
