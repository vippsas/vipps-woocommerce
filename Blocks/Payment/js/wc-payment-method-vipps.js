
(function () { 


// Imports
const  __  = wp.i18n.__;
const decodeEntities  = wp.htmlEntities.decodeEntities;
const getSetting  = wc.wcSettings.getSetting;
const registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;


// Data
const settings = getSetting('vipps_data', {});
const defaultLabel = __('Vipps', 'woo-vipps');
const label = decodeEntities(settings.title) || defaultLabel;


const Content = () => {
	return React.createElement(
		'div',
		null,
		decodeEntities(settings.description || '')
	);
};

const Label = props => {
	const { PaymentMethodLabel } = props.components;
	return React.createElement(PaymentMethodLabel, { text: label });
};

const canMakePayment = (args) => {
 return true;
};

/**
 * Cash on Delivery (COD) payment method config object.
 */
const VippsPaymentMethod = {
name: 'vipps',
      label: React.createElement(Label, null),
      content: React.createElement(Content, null),
      edit: React.createElement(Content, null),
      placeOrderButtonLabel: __(
              'Continue with Vipps',
              'woo-vipps'
              ),
      	icons: [],
      canMakePayment: canMakePayment,
      ariaLabel: label
};


registerPaymentMethod(Config => new Config(VippsPaymentMethod));

}());
