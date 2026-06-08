
(function () { 


// Imports
const { __ } = wp.i18n;
const { decodeEntities }  = wp.htmlEntities;
const { getSetting }  = wc.wcSettings;
const { registerPaymentMethod }  = wc.wcBlocksRegistry;
const { applyFilters } = wp.hooks;

//( 'hookName', content, arg1, arg2, ... )


// Data
const settings = getSetting('vipps_card_data', {});
const defaultLabel = VippsLocale['pay_with_card'];
const label = decodeEntities(settings.title) || defaultLabel;
const iconsrc = settings.iconsrc;


const Content = () => {
        var content = React.createElement(
		'div',
		null,
		decodeEntities(settings.description || '')
	);
       return applyFilters('woo_vipps_card_checkout_description', content, settings);
};

const Label = props => {
        const { PaymentMethodLabel } = props.components;
        let textlabel = React.createElement( 'span', null, decodeEntities(settings.title || ''));
        let icon = React.createElement('img', { style: {display: 'inline-block'}, alt: textlabel, title: textlabel, className: 'vipps-card-payment-logo', src:iconsrc});
        let label =  React.createElement(PaymentMethodLabel, { text: textlabel, icon: icon });
        return applyFilters('woo_vipps_card_checkout_label', label, settings);
};

const canMakePayment = (args) => {
        var candoit = true;
        return applyFilters('woo_vipps_card_checkout_block_show_vipps', candoit, settings);
};

/**
 * Vipps  payment method config object.
 */
const VippsCardPaymentMethod = {
        name: 'vipps_card',
        label: React.createElement(Label, null),
        content: React.createElement(Content, null),
        edit: React.createElement(Content, null),
        placeOrderButtonLabel: VippsLocale['pay_with_card'],
        icons: null,
        canMakePayment: canMakePayment,
        ariaLabel: label
};

registerPaymentMethod(VippsCardPaymentMethod);


}());
