export interface NotificationBannerProps {
  variant: 'success' | 'warning' | 'error';
  text: string | string[];
}

/**
 * A notification banner to display messages to the user.
 * 
 * @param variant The variant of the notification banner.
 * @param text The text to display in the notification banner.
 */
export function NotificationBanner({ variant, text }: React.PropsWithChildren<NotificationBannerProps>): JSX.Element {
  return (
    <div className={`vipps-mobilepay-react-notification-banner vipps-mobilepay-react-notification-banner-${variant}`}>
      {/* In case the text is an array, renders the individual text items as paragraphs, otherwise lists a single paragraph */}
      {Array.isArray(text) ? text.map((str, idx) => <p key={idx}>{str}</p>) : <p>{text}</p>}
    </div>
  );
}
