import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App.tsx';

/**
 * Renders the Vipps MobilePay React UI component into the specified DOM element.
 *
 * @param {string} elementId - The ID of the DOM element to render the component into.
 * @returns {void}
 */
ReactDOM.createRoot(document.getElementById('vipps-mobilepay-react-ui')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
