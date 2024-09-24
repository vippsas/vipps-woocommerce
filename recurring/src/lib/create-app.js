import { createRoot, render } from "@wordpress/element";

export default function createApp(element, component) {
  if (createRoot) {
    const root = createRoot(element);
    root.render(component);
  } else {
    render(component, element);
  }
}
