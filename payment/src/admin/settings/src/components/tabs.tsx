import { useRef } from 'react';

interface Props {
  tabs: string[];
  onTabChange: (tab: string) => void;
  activeTab: string;
}

/** A tab list with arrow, Home, and End key navigation. */
export function Tabs({ tabs, onTabChange, activeTab }: Props): JSX.Element {
  const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);

  function handleKeyDown(event: React.KeyboardEvent<HTMLButtonElement>, index: number) {
    let nextIndex: number;
    switch (event.key) {
      case 'ArrowRight':
        nextIndex = (index + 1) % tabs.length;
        break;
      case 'ArrowLeft':
        nextIndex = (index - 1 + tabs.length) % tabs.length;
        break;
      case 'Home':
        nextIndex = 0;
        break;
      case 'End':
        nextIndex = tabs.length - 1;
        break;
      default:
        return;
    }
    event.preventDefault();
    onTabChange(tabs[nextIndex]);
    tabRefs.current[nextIndex]?.focus();
  }

  return (
    <div className="vippstabholder" role="tablist">
      {tabs.map((tab, index) => (
        <button
          key={tab}
          ref={(element) => { tabRefs.current[index] = element; }}
          type="button"
          role="tab"
          aria-selected={tab === activeTab}
          tabIndex={tab === activeTab ? 0 : -1}
          className={`vipps-mobilepay-react-tab ${tab === activeTab ? 'active' : ''}`}
          onClick={() => onTabChange(tab)}
          onKeyDown={(event) => handleKeyDown(event, index)}
        >
          {tab}
        </button>
      ))}
    </div>
  );
}
