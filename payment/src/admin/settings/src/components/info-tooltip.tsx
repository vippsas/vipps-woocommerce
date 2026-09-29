import { UnsafeHtmlText } from './unsafe-html-text';

interface InfoTooltipProps {
  html: string;
}

/** Displays help text when the info icon is hovered or focused. */
export function InfoTooltip({ html }: InfoTooltipProps): JSX.Element {
  return (
    <span className="vipps-mobilepay-react-info-tooltip">
      <button type="button" className="vipps-mobilepay-react-info-tooltip-trigger">
        <span aria-hidden="true">?</span>
      </button>
      <span className="vipps-mobilepay-react-info-tooltip-content">
        <UnsafeHtmlText htmlString={html} />
      </span>
    </span>
  );
}
