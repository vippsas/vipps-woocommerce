import { ComponentProps } from 'react';
import { getMetadata } from '../../lib/wp-data';

export function PageShell({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
  return <div className={`vipps-settings-shell ${getMetadata('payment_method') === 'MobilePay' ? 'MobilePay' : ''}`}>
    <header className="vipps-settings-header"><h1>{title}</h1>{description && <p>{description}</p>}</header>
    <div className="vipps-settings-content">{children}</div>
  </div>;
}

export function PostForm({ action, children, ...props }: ComponentProps<'form'> & { action?: string }) {
  return <form method="post" action={getMetadata('post_url') ?? ''} {...props}>
    <input type="hidden" name="action" value={action} />
    <input type="hidden" name={getMetadata('nonce_name') ?? ''} value={getMetadata('nonce') ?? ''} />
    {children}
  </form>;
}

export function RichText({ html }: { html: string }) {
  return <span dangerouslySetInnerHTML={{ __html: html }} />;
}
