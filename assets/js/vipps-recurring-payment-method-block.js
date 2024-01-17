(function () {
  registerVippsRecurringGateway()
}());

function registerVippsRecurringGateway() {
  //const {__} = wp.i18n; // todo: use this instead of VippsRecurringLocale

  const {decodeEntities} = window.wp.htmlEntities;
  const {getSetting} = window.wc.wcSettings;
  const {registerPaymentMethod} = window.wc.wcBlocksRegistry;
  const {applyFilters} = window.wp.hooks;

  const settings = getSetting('vipps_recurring_data', {});
  const defaultLabel = VippsRecurringLocale['Vipps'];
  const ariaLabel = decodeEntities(settings.title) || defaultLabel;
  const logo = settings.logo;

  const Content = () => {
    const content = React.createElement('div', null, decodeEntities(settings.description || ''));
    return applyFilters('woo_vipps_recurring_checkout_description', content, settings);
  };

  const Label = props => {
    const {PaymentMethodLabel} = props.components;
    const textlabel = React.createElement('span', null, decodeEntities(settings.title || ''));

    const icon = React.createElement('img', {
      alt: textlabel,
      title: textlabel,
      className: 'vipps-recurring-payment-logo',
      src: logo
    });

    const label = React.createElement(PaymentMethodLabel, {
      text: textlabel,
      icon: icon
    });

    return applyFilters('woo_vipps_recurring_checkout_label', label, settings);
  };

  const canMakePayment = (args) => {
    return applyFilters('woo_vipps_recurring_checkout_show_gateway', true, settings);
  };

  /**
   * Vipps Recurring payment method config object.
   */
  const paymentMethod = {
    name: 'vipps_recurring',
    paymentMethodId: 'vipps_recurring',
    placeOrderButtonLabel: VippsRecurringLocale['Continue with %s'],
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

  console.log(paymentMethod)

  registerPaymentMethod(paymentMethod);
}
