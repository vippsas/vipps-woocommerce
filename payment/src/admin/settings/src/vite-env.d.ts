/// <reference types="vite/client" />

declare namespace JSX {
  interface IntrinsicElements {
    'vipps-mobilepay-badge': React.DetailedHTMLProps<React.HTMLAttributes<HTMLElement>, HTMLElement> & { brand?: string; language?: string; variant?: string };
    'vipps-mobilepay-button': React.DetailedHTMLProps<React.HTMLAttributes<HTMLElement>, HTMLElement> & { type?: string; brand?: string; language?: string; variant?: string; rounded?: string; compact?: string; stretched?: string; verb?: string };
  }
}
