import { PropsWithChildren } from 'react';

/**
 * Props for the HtmlToReactConverter component.
 */
interface HtmlToReactConverterProps {
  htmlString: string;
  className?: string;
}

/**
 * Converts a HTML string to React nodes, with support for some basic HTML tags.
 * It uses the dangerouslySetInnerHTML prop to render the HTML string directly.
 * 
 * ### This is **NOT** recommended to be used for user input, as it can lead to XSS attacks.
 * It should primarily be used on "safe" input, such as translations which do not embed any kind of user input.
 *
 * @returns A React node with the converted HTML string.
 */
export function UnsafeHtmlText({ htmlString, className = '' }: PropsWithChildren<HtmlToReactConverterProps>): JSX.Element {
  return <span className={[className].join(' ')} dangerouslySetInnerHTML={{ __html: htmlString }} />;
}
