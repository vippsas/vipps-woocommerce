import { PropsWithChildren } from 'react';

interface collapsibleProps {
  title: string;
  defaultOpen?: boolean;
}

/** A collapsible collapsible-style section. LP 2026-09-25 */
export function Collapsible({ title, defaultOpen = false, children }: PropsWithChildren<collapsibleProps>): JSX.Element {
  return (
    <details className="vipps-mobilepay-react-collapsible" open={defaultOpen}>
      <summary className="vipps-mobilepay-react-collapsible-title">{title}</summary>
      <div className="vipps-mobilepay-react-collapsible-content">{children}</div>
    </details>
  );
}
