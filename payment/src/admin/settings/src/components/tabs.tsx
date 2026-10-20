/**
 * Props for the Tabs component.
 */
interface Props {
  /**
   * An array of tab names.
   */
  tabs: string[];

  /**
   * A callback function that is called when the active tab is changed.
   * @param tab - The name of the new active tab.
   */
  onTabChange: (tab: string) => void;

  /**
   * The name of the currently active tab.
   */
  activeTab: string;
}

/**
 * Renders a set of tabs, with the ability to switch between them.
 * @returns The rendered set of tabs.
 */
export function Tabs({ tabs, onTabChange, activeTab }: Props): JSX.Element {
  return (
    <div className="vippstabholder" role="tablist">
      {tabs.map((tab, index) => (
        <button
          key={tab}
          id={`vipps-settings-tab-${index}`}
          type="button"
          role="tab"
          aria-selected={tab === activeTab}
          aria-controls="vipps-settings-tab-panel"
          className={`vipps-mobilepay-react-tab ${tab === activeTab ? 'active' : ''}`}
          onClick={() => onTabChange(tab)}
        >
          {tab}
        </button>
      ))}
    </div>
  );
}
