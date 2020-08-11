
(function () { 


// Imports
const  __  = wp.i18n.__;
const decodeEntities  = wp.htmlEntities.decodeEntities;
const getSetting  = wc.wcSettings.getSetting;
const registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;


// Data
const settings = getSetting('vipps_data', {});
const defaultLabel = VippsLocale['Vipps'];
const label = decodeEntities(settings.title) || defaultLabel;


const Content = () => {
	return React.createElement(
		'div',
		null,
		decodeEntities(settings.description || '')
	);
};

const Label = props => {
//	const { PaymentMethodLabel } = props.components;
        const icon = React.createElement('img', { alt: label, title: label, className: 'vipps-payment-logo', src:"https://vdev.digitalt.org/wp-content/plugins/woo-vipps/img/vipps_logo_rgb.png"});

        return icon;
//	return React.createElement(PaymentMethodLabel, { text: label, icon: icon });
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
      placeOrderButtonLabel: VippsLocale['Continue with Vipps'],
      icons: [],
      canMakePayment: canMakePayment,
      ariaLabel: label
};


registerPaymentMethod(Config => new Config(VippsPaymentMethod));


/*
export const PaymentMethodLabel = ( { icon = '', text = '' } ) => {
        const hasIcon = !! icon;
        const hasNamedIcon =
                hasIcon && typeof icon === 'string' && namedIcons[ icon ];
        const className = classnames( 'wc-block-components-payment-method-label', {
                'wc-block-components-payment-method-label--with-icon': hasIcon,
        } );

        return (
                <span className={ className }>
                        { hasNamedIcon ? <Icon srcElement={ namedIcons[ icon ] } /> : icon }
                        { text }
                </span>
        );
};
*/

}());
