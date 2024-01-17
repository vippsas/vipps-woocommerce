
(function () { 


// Imports
const { __ } = wp.i18n;
const { decodeEntities }  = wp.htmlEntities;
const { getSetting }  = wc.wcSettings;
const { registerPaymentMethod }  = wc.wcBlocksRegistry;
const { registerExpressPaymentMethod }  = wc.wcBlocksRegistry;
const { applyFilters } = wp.hooks;

//( 'hookName', content, arg1, arg2, ... )


// Data
const settings = getSetting('vipps_data', {});
const defaultLabel = VippsLocale['Vipps'];
const label = decodeEntities(settings.title) || defaultLabel;
const iconsrc = settings.iconsrc;


const Content = () => {
        var content = React.createElement(
		'div',
		null,
		decodeEntities(settings.description || '')
	);
       return applyFilters('woo_vipps_checkout_description', content, settings);
};

const Label = props => {
        const { PaymentMethodLabel } = props.components;
        let textlabel = React.createElement( 'span', null, decodeEntities(settings.title || ''));
        let icon = React.createElement('img', { style: {display: 'inline-block'}, alt: textlabel, title: textlabel, className: 'vipps-payment-logo', src:iconsrc});
        let label =  React.createElement(PaymentMethodLabel, { text: textlabel, icon: icon });
        return applyFilters('woo_vipps_checkout_label', label, settings);
};

const ExpressCheckoutButton = props => {
 var expressbutton = React.createElement('div', {dangerouslySetInnerHTML: {__html: settings.expressbutton  },  className: 'vipps-express-container'}, null);
 return applyFilters('woo_vipps_checkout_block_express_button', expressbutton, settings);
}

const canMakeExpressPayment = (args) => {
 var candoit = settings.show_express_checkout;
 return applyFilters('woo_vipps_checkout_block_show_express_checkout', candoit, settings);
};

const canMakePayment = (args) => {
 var candoit = true;
 return applyFilters('woo_vipps_checkout_block_show_vipps', candoit, settings);
};

/**
 * Vipps  payment method config object.
 */
const VippsPaymentMethod = {
      name: 'vipps',
      label: React.createElement(Label, null),
      content: React.createElement(Content, null),
      edit: React.createElement(Content, null),
      placeOrderButtonLabel: VippsLocale['Continue with Vipps'],
      icons: null,
      canMakePayment: canMakePayment,
      ariaLabel: label
};
const VippsExpressPaymentMethod = {
      name: 'vippsexpress',
      content: React.createElement(ExpressCheckoutButton, null),
      edit: React.createElement(ExpressCheckoutButton, null),
      paymentMethodId: 'vipps',
      canMakePayment: canMakeExpressPayment,
};


registerPaymentMethod(VippsPaymentMethod);
registerExpressPaymentMethod(VippsExpressPaymentMethod);


}());
