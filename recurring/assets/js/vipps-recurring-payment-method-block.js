(function () {
  registerVippsRecurringGateway()
}());

function registerVippsRecurringGateway() {
  const {decodeEntities} = window.wp.htmlEntities;
  const {getSetting} = window.wc.wcSettings;
  const {registerPaymentMethod} = window.wc.wcBlocksRegistry;
  const {applyFilters} = window.wp.hooks;

  const settings = getSetting('vipps_recurring_data', {});
  const ariaLabel = decodeEntities(settings.title);
  const logo = settings.logo;

  const Content = () => {
    const content = React.createElement('div', null, decodeEntities(settings.description || ''));
    return applyFilters('wc_vipps_recurring_checkout_description', content, settings);
  };

  const Label = props => {
    const { PaymentMethodLabel } = props.components;

    const title = decodeEntities(settings.title || '')
    let label;

    if (logo && logo.length > 0) {
      label = React.createElement('img', { alt: title, title: title, className: 'vipps-recurring-payment-logo', src: logo });
    } else {
      label = React.createElement(PaymentMethodLabel, {
        text: React.createElement('span', null, title)
      });
    }

    return applyFilters('wc_vipps_recurring_checkout_label', label, settings);
  };

  const canMakePayment = () => {
    return applyFilters('wc_vipps_recurring_checkout_show_gateway', true, settings);
  };

  /**
   * Vipps/MobilePay Recurring payment method config object.
   */
  const paymentMethod = {
    name: 'vipps_recurring',
    // translators: %s: brand name, Vipps or MobilePay
    placeOrderButtonLabel: wp.i18n.sprintf(wp.i18n.__("Continue with %s", 'woo-vipps'), settings.title),
    supports: {
      features: settings.supports || []
    },
    label: React.createElement(Label, null),
    content: React.createElement(Content, null),
    edit: React.createElement(Content, null),
    icons: null,
    canMakePayment,
    ariaLabel
  };

  registerPaymentMethod(paymentMethod);
}
