interface Props {
  variant: 'success' | 'warning' | 'error';
}
export function NotificationBanner({ variant, children }: React.PropsWithChildren<Props>): JSX.Element {
  return <div className={['vipps-mobilepay-react-notification-banner', variant].join(' ')}>{children}</div>;
}
