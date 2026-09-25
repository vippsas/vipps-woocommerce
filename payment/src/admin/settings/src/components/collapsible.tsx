import { PropsWithChildren } from 'react';

interface AccordionProps {
  title: string;
  defaultOpen?: boolean;
}

/** A collapsible accordion-style section. LP 2026-09-25 */
export function Collapsible({ title, defaultOpen = false, children }: PropsWithChildren<AccordionProps>): JSX.Element {
  return (
    <details className="vipps-mobilepay-react-accordion" open={defaultOpen}>
      <summary className="vipps-mobilepay-react-accordion-title">{title}</summary>
      <div className="vipps-mobilepay-react-accordion-content">{children}</div>
    </details>
  );
}
