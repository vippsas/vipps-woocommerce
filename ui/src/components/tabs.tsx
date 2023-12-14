interface Props {
  tabs: string[];
  onTabChange: (tab: string) => void;
  activeTab: string;
}
export function Tabs({ tabs, onTabChange, activeTab }: Props) {
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
