/**
 * Props for the Tabs component.
 */
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
    <div className="vippstabholder" id="vippstabholder">
      {tabs.map((tab, index) => (
        <h3
          key={index}
          id={`woocommerce_vipps_${tab}_options`}
          aria-selected={tab === activeTab ? 'true' : 'false'}
          className={`wc-settings-sub-title tab ${tab === activeTab ? 'active' : ''}`}
          title={tab}
          onClick={() => onTabChange(tab)}
          style={{ cursor: 'pointer' }}
        >
          {tab}
        </h3>
      ))}
    </div>
  );
}
