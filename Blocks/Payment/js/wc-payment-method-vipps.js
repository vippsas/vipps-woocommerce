
(function () { 


// Imports
const { __ } = wp.i18n;
const { decodeEntities }  = wp.htmlEntities;
const { getSetting }  = wc.wcSettings;
const { registerPaymentMethod }  = wc.wcBlocksRegistry;
const { registerExpressPaymentMethod }  = wc.wcBlocksRegistry;


// Data
const settings = getSetting('vipps_data', {});
const defaultLabel = VippsLocale['Vipps'];
const label = decodeEntities(settings.title) || defaultLabel;
const iconsrc = settings.iconsrc;


const Content = () => {
	return React.createElement(
		'div',
		null,
		decodeEntities(settings.description || '')
	);
};

const Label = props => {

        if (iconsrc != '') {
            const icon = React.createElement('img', { alt: label, title: label, className: 'vipps-payment-logo', src:iconsrc});
            return icon;
        }

        // Just do a text label if no icon is passed (this is filterable) IOK 2020-08-10
	const { PaymentMethodLabel } = props.components;
	return React.createElement(PaymentMethodLabel, { text: label, icon: icon });
};

const ExpressCheckoutButton = props => {
 return React.createElement('div', {dangerouslySetInnerHTML: {__html: settings.expressbutton  },  className: 'vipps-express-container'}, null);
}

const canMakeExpressPayment = (args) => {
 return settings.expressbutton != "";
};

const canMakePayment = (args) => {
 return true;
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


registerPaymentMethod(Config => new Config(VippsPaymentMethod));
registerExpressPaymentMethod(Config => new Config(VippsExpressPaymentMethod));


}());
