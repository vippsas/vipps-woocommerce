import { PropsWithChildren } from 'react';

interface AccordionProps {
  title: string;
  defaultOpen?: boolean;
}

/** A collapsible section that keeps its form fields mounted while closed. */
export function Accordion({ title, defaultOpen = false, children }: PropsWithChildren<AccordionProps>): JSX.Element {
  return (
    <details className="vipps-mobilepay-react-accordion" open={defaultOpen}>
      <summary className="vipps-mobilepay-react-accordion-title">{title}</summary>
      <div className="vipps-mobilepay-react-accordion-content">{children}</div>
    </details>
  );
}
