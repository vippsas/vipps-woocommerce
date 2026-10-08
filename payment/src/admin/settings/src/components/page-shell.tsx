import { ComponentProps, ReactNode } from 'react';
import { getMetadata } from '../lib/wp-data';

type PageShellProps = {
  companyName: string;
  subtitle: string;
  paymentMethod: string;
  children: ReactNode;
};

export function PageShell({ companyName, subtitle, paymentMethod, children }: PageShellProps) {
  return <div className={`vipps-admin-shell ${paymentMethod}`}>
    <header className="vipps-admin-header">
      <h1>{companyName}</h1>
      <p className="vipps-admin-subtitle">{subtitle}</p>
    </header>
    {children}
  </div>;
}

export function PostForm({ action, children, disabled = false, ...props }: ComponentProps<'form'> & { action?: string; disabled?: boolean }) {
  return <form method="post" action={getMetadata('post_url') ?? ''} aria-busy={disabled} {...props}>
    <input type="hidden" name="action" value={action} />
    <input type="hidden" name={getMetadata('nonce_name') ?? ''} value={getMetadata('nonce') ?? ''} />
    <fieldset disabled={disabled} style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}>
      {children}
    </fieldset>
  </form>;
}

export function RichText({ html }: { html: string }) {
  return <span dangerouslySetInnerHTML={{ __html: html }} />;
}
