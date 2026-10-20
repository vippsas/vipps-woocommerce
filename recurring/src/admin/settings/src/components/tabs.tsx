
export interface SettingsTab { id: string; title: string; fields: string[] }
interface Props {
  tabs: SettingsTab[];
  activeTab: string;
  onTabChange: (tab: string) => void;
}

/** Payment-style navigation with stable IDs and keyboard tab-list navigation. */
export function Tabs({ tabs, activeTab, onTabChange }: Props): JSX.Element {
  return (
    <div className="vippstabholder" role="tablist">
      {tabs.map((tab, index) => (
        <button
          key={tab.id} id={`vipps-recurring-tab-${tab.id}`} type="button" role="tab"
          aria-selected={tab.id === activeTab} aria-controls="vipps-recurring-tab-panel"
          tabIndex={tab.id === activeTab ? 0 : -1}
          className={`vipps-mobilepay-react-tab ${tab.id === activeTab ? 'active' : ''}`}
          onClick={() => onTabChange(tab.id)}
          onKeyDown={(event) => {
            // Support both the desktop side navigation and the narrow-screen row.
            const movement: Record<string, number> = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
            let next = index;
            if (event.key in movement) next = (index + movement[event.key] + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else return;
            event.preventDefault();
            onTabChange(tabs[next].id);
            document.getElementById(`vipps-recurring-tab-${tabs[next].id}`)?.focus();
          }}
        >{tab.title}</button>
      ))}
    </div>
  );
}
